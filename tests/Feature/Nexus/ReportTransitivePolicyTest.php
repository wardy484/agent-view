<?php

declare(strict_types=1);

use App\Enums\SnapshotVisibility;
use App\Models\Snapshot;
use App\Models\SnapshotShare;
use App\Models\User;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use App\Policies\SnapshotPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function reportTransitivePolicy(): SnapshotPolicy
{
    return app(SnapshotPolicy::class);
}

/**
 * @return array{0: Snapshot, 1: Snapshot} [report, embeddedTable]
 */
function reportWithEmbed(User $owner): array
{
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);

    $table = Snapshot::factory()->for($workbench)->create(['slug' => 'tbl']);
    SnapshotVersioning::append(
        $table,
        'table',
        ['columns' => [['key' => 'a', 'label' => 'A']], 'rows' => [['a' => '1']]],
    );

    $report = Snapshot::factory()->for($workbench)->create(['slug' => 'rpt']);
    SnapshotVersioning::append(
        $report,
        'report',
        ['blocks' => [
            ['type' => 'markdown', 'body' => '# Hi'],
            ['type' => 'embed', 'snapshot_id' => $table->id],
        ]],
    );

    return [$report->fresh(), $table->fresh()];
}

it('REQ-M5-004: a user with a share on the report can view the embedded snapshot transitively', function (): void {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    [$report, $table] = reportWithEmbed($owner);

    // Stranger has no direct read on the table.
    expect(reportTransitivePolicy()->view($viewer, $table))->toBeFalse();

    // Share the report (only) with the viewer.
    $report->setVisibility(SnapshotVisibility::Shared);
    SnapshotShare::query()->create([
        'snapshot_id' => $report->id,
        'email' => strtolower((string) $viewer->email),
        'user_id' => $viewer->id,
        'granted_by_user_id' => $owner->id,
    ]);

    // Now the viewer can read the embedded table transitively via the report share.
    expect(reportTransitivePolicy()->view($viewer->fresh(), $table->fresh()))->toBeTrue();
});

it('REQ-M5-004: a viewer with a link-share token on the report can view the embed transitively', function (): void {
    $owner = User::factory()->create();
    [$report, $table] = reportWithEmbed($owner);

    $report->setVisibility(SnapshotVisibility::Link);
    $token = $report->fresh()->share_token;

    // Direct guest visit on the table fails.
    expect(reportTransitivePolicy()->view(null, $table->fresh()))->toBeFalse();

    // But guest-with-report-token transitively gains read.
    expect(reportTransitivePolicy()->view(null, $table->fresh(), $token))->toBeTrue();
});

it('REQ-M5-004: revoking the share on the report revokes transitive access to the embed', function (): void {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    [$report, $table] = reportWithEmbed($owner);

    $report->setVisibility(SnapshotVisibility::Shared);
    $share = SnapshotShare::query()->create([
        'snapshot_id' => $report->id,
        'email' => strtolower((string) $viewer->email),
        'user_id' => $viewer->id,
        'granted_by_user_id' => $owner->id,
    ]);

    expect(reportTransitivePolicy()->view($viewer->fresh(), $table->fresh()))->toBeTrue();

    $share->revoke();

    expect(reportTransitivePolicy()->view($viewer->fresh(), $table->fresh()))->toBeFalse();
});

it('REQ-M5-004: dropping the embed in a new report revision revokes transitive access', function (): void {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    [$report, $table] = reportWithEmbed($owner);

    $report->setVisibility(SnapshotVisibility::Shared);
    SnapshotShare::query()->create([
        'snapshot_id' => $report->id,
        'email' => strtolower((string) $viewer->email),
        'user_id' => $viewer->id,
        'granted_by_user_id' => $owner->id,
    ]);

    expect(reportTransitivePolicy()->view($viewer->fresh(), $table->fresh()))->toBeTrue();

    // Author edits the report and drops the embed.
    SnapshotVersioning::append(
        $report,
        'report',
        ['blocks' => [['type' => 'markdown', 'body' => '# now embedless']]],
    );

    expect(reportTransitivePolicy()->view($viewer->fresh(), $table->fresh()))->toBeFalse();
});

it('REQ-M5-004: a user with no access to any embedding report sees no transitive grant', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    [, $table] = reportWithEmbed($owner);

    expect(reportTransitivePolicy()->view($stranger, $table))->toBeFalse();
});

it('REQ-M5-004: a stale pin from a non-current report revision does not grant access', function (): void {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    [$report, $table] = reportWithEmbed($owner);

    $report->setVisibility(SnapshotVisibility::Shared);
    SnapshotShare::query()->create([
        'snapshot_id' => $report->id,
        'email' => strtolower((string) $viewer->email),
        'user_id' => $viewer->id,
        'granted_by_user_id' => $owner->id,
    ]);

    // Drop the embed by appending a markdown-only revision; the original
    // pin row in snapshot_embeds remains in place but it now points at a
    // non-current report version. The policy must ignore stale pins.
    SnapshotVersioning::append(
        $report,
        'report',
        ['blocks' => [['type' => 'markdown', 'body' => '# nothing']]],
    );

    expect(reportTransitivePolicy()->view($viewer->fresh(), $table->fresh()))->toBeFalse();
});
