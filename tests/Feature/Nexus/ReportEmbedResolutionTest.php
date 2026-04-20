<?php

declare(strict_types=1);

use App\Enums\SnapshotVisibility;
use App\Models\Snapshot;
use App\Models\SnapshotShare;
use App\Models\User;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('REQ-M5-007: snapshot page inlines embed payloads with pinned + current revisions', function (): void {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id, 'slug' => 'rep-wb']);

    $table = Snapshot::factory()->for($workbench)->create(['slug' => 'tbl', 'title' => 'Sales']);
    $tableV1 = SnapshotVersioning::append(
        $table,
        'table',
        ['columns' => [['key' => 'a', 'label' => 'A']], 'rows' => [['a' => 'first']]],
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

    // Bump the embedded snapshot — pin should still resolve to v1.
    $tableV2 = SnapshotVersioning::append(
        $table,
        'table',
        ['columns' => [['key' => 'a', 'label' => 'A']], 'rows' => [['a' => 'updated']]],
    );

    $this->actingAs($owner)
        ->withoutVite()
        ->get(route('workbench.snapshot.show', [
            'workbench' => $workbench->slug,
            'snapshot' => $report->slug,
        ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('snapshot')
            ->where('version.view_type', 'report')
            ->has('version.resolved_blocks', 2)
            ->where('version.resolved_blocks.0.type', 'markdown')
            ->where('version.resolved_blocks.0.body', '# Hi')
            ->where('version.resolved_blocks.1.type', 'embed')
            ->where('version.resolved_blocks.1.snapshot_id', $table->id)
            ->where('version.resolved_blocks.1.view_type', 'table')
            ->where('version.resolved_blocks.1.title', 'Sales')
            ->where('version.resolved_blocks.1.pinned_version_id', $tableV1->id)
            ->where('version.resolved_blocks.1.pinned_revision', 1)
            ->where('version.resolved_blocks.1.current_revision', 2)
            ->where('version.resolved_blocks.1.is_stale', true)
            ->has('version.resolved_blocks.1.data_payload'));
});

it('REQ-M5-007: non-report snapshots receive no resolved_blocks prop', function (): void {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);

    $snap = Snapshot::factory()->for($workbench)->create();
    SnapshotVersioning::append(
        $snap,
        'table',
        ['columns' => [['key' => 'a', 'label' => 'A']], 'rows' => [['a' => 'x']]],
    );

    $this->actingAs($owner)
        ->withoutVite()
        ->get(route('workbench.snapshot.show', [
            'workbench' => $workbench->slug,
            'snapshot' => $snap->slug,
        ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('version.view_type', 'table')
            ->missing('version.resolved_blocks'));
});

it('REQ-M5-007: a transitively-shared report exposes embed payloads to a shared viewer', function (): void {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();

    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id, 'slug' => 'shared-wb']);

    $table = Snapshot::factory()->for($workbench)->create(['slug' => 'tbl', 'title' => 'KPIs']);
    SnapshotVersioning::append(
        $table,
        'table',
        ['columns' => [['key' => 'a', 'label' => 'A']], 'rows' => [['a' => '42']]],
    );

    $report = Snapshot::factory()->for($workbench)->create(['slug' => 'rpt']);
    SnapshotVersioning::append(
        $report,
        'report',
        ['blocks' => [['type' => 'embed', 'snapshot_id' => $table->id]]],
    );

    $report->setVisibility(SnapshotVisibility::Shared);
    SnapshotShare::query()->create([
        'snapshot_id' => $report->id,
        'email' => strtolower((string) $viewer->email),
        'user_id' => $viewer->id,
        'granted_by_user_id' => $owner->id,
    ]);

    $this->actingAs($viewer)
        ->withoutVite()
        ->get(route('workbench.snapshot.show', [
            'workbench' => $workbench->slug,
            'snapshot' => $report->slug,
        ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('version.view_type', 'report')
            ->where('version.resolved_blocks.0.type', 'embed')
            ->where('version.resolved_blocks.0.title', 'KPIs')
            ->has('version.resolved_blocks.0.data_payload'));
});
