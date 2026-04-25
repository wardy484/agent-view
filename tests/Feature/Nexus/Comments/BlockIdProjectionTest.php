<?php

declare(strict_types=1);

use App\Models\Snapshot;
use App\Models\User;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('REQ-M6-026: SnapshotController@show projects markdown block ids in resolved_blocks', function (): void {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id, 'slug' => 'wb-026a']);

    $report = Snapshot::factory()->for($workbench)->create(['slug' => 'rpt-026a']);
    SnapshotVersioning::append(
        $report,
        'report',
        ['blocks' => [
            ['type' => 'markdown', 'body' => 'First paragraph.'],
            ['type' => 'markdown', 'body' => 'Second paragraph.'],
        ]],
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
            ->where('version.resolved_blocks.1.type', 'markdown')
            ->has('version.resolved_blocks.0.id')
            ->has('version.resolved_blocks.1.id')
            ->where('version.resolved_blocks.0.id', fn ($id) => is_string($id) && Str::isUuid($id))
            ->where('version.resolved_blocks.1.id', fn ($id) => is_string($id) && Str::isUuid($id)));
});

it('REQ-M6-026: resolved_blocks markdown id matches the original payload id', function (): void {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id, 'slug' => 'wb-026b']);

    $report = Snapshot::factory()->for($workbench)->create(['slug' => 'rpt-026b']);
    $version = SnapshotVersioning::append(
        $report,
        'report',
        ['blocks' => [
            ['type' => 'markdown', 'body' => 'Round-trip me.'],
            ['type' => 'markdown', 'body' => 'And me too.'],
        ]],
    );

    // Pull the canonical ids the validator assigned, then prove the projection
    // forwards them verbatim — same UUIDs reach the frontend.
    $storedBlocks = $version->data_payload['blocks'];
    $expectedIds = array_column($storedBlocks, 'id');

    expect($expectedIds)->toHaveCount(2);
    foreach ($expectedIds as $id) {
        expect($id)->toBeString();
        expect(Str::isUuid($id))->toBeTrue();
    }

    $this->actingAs($owner)
        ->withoutVite()
        ->get(route('workbench.snapshot.show', [
            'workbench' => $workbench->slug,
            'snapshot' => $report->slug,
        ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('version.resolved_blocks.0.id', $expectedIds[0])
            ->where('version.resolved_blocks.1.id', $expectedIds[1])
            ->where('version.resolved_blocks.0.body', 'Round-trip me.')
            ->where('version.resolved_blocks.1.body', 'And me too.'));
});

it('REQ-M6-026: resolved_blocks embed entries also carry id', function (): void {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id, 'slug' => 'wb-026c']);

    $table = Snapshot::factory()->for($workbench)->create(['slug' => 'tbl-026c', 'title' => 'Numbers']);
    SnapshotVersioning::append(
        $table,
        'table',
        ['columns' => [['key' => 'a', 'label' => 'A']], 'rows' => [['a' => 'one']]],
    );

    $report = Snapshot::factory()->for($workbench)->create(['slug' => 'rpt-026c']);
    $reportVersion = SnapshotVersioning::append(
        $report,
        'report',
        ['blocks' => [
            ['type' => 'markdown', 'body' => 'Intro.'],
            ['type' => 'embed', 'snapshot_id' => $table->id],
        ]],
    );

    $storedBlocks = $reportVersion->data_payload['blocks'];
    $expectedEmbedId = $storedBlocks[1]['id'] ?? null;
    expect($expectedEmbedId)->toBeString();
    expect(Str::isUuid($expectedEmbedId))->toBeTrue();

    $this->actingAs($owner)
        ->withoutVite()
        ->get(route('workbench.snapshot.show', [
            'workbench' => $workbench->slug,
            'snapshot' => $report->slug,
        ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('version.resolved_blocks.1.type', 'embed')
            ->where('version.resolved_blocks.1.id', $expectedEmbedId));
});

it('REQ-M6-026: snapshots without ids on markdown blocks (legacy) project null id (until backfill runs)', function (): void {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id, 'slug' => 'wb-026d']);

    $report = Snapshot::factory()->for($workbench)->create(['slug' => 'rpt-026d']);

    // Insert a legacy report version directly via DB::table(), bypassing the
    // ReportViewSchema validator that auto-assigns block ids — simulating
    // historical rows pre-dating REQ-M6-001.
    $now = now();
    $versionId = DB::table('snapshot_versions')->insertGetId([
        'snapshot_id' => $report->id,
        'revision' => 1,
        'view_type' => 'report',
        'data_payload' => json_encode([
            'blocks' => [
                ['type' => 'markdown', 'body' => 'Legacy paragraph one.'],
                ['type' => 'markdown', 'body' => 'Legacy paragraph two.'],
            ],
        ]),
        'metadata' => null,
        'preview_html' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $report->forceFill(['current_version_id' => $versionId])->save();

    $this->actingAs($owner)
        ->withoutVite()
        ->get(route('workbench.snapshot.show', [
            'workbench' => $workbench->slug,
            'snapshot' => $report->slug,
        ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('version.view_type', 'report')
            ->has('version.resolved_blocks', 2)
            ->where('version.resolved_blocks.0.type', 'markdown')
            ->where('version.resolved_blocks.0.id', null)
            ->where('version.resolved_blocks.1.type', 'markdown')
            ->where('version.resolved_blocks.1.id', null));
});
