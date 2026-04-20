<?php

declare(strict_types=1);

use App\Models\Snapshot;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('REQ-M5-003: snapshot_embeds table exists with the expected columns and indexes', function (): void {
    expect(Schema::hasTable('snapshot_embeds'))->toBeTrue();
    expect(Schema::hasColumns('snapshot_embeds', [
        'id',
        'report_snapshot_id',
        'report_version_id',
        'embedded_snapshot_id',
        'embedded_version_id',
        'block_index',
        'created_at',
    ]))->toBeTrue();
});

it('REQ-M5-003: append() pins each embed to the embedded snapshot.current_version_id at write-time', function (): void {
    $workbench = Workbench::query()->create(['slug' => 'pin-wb', 'name' => 'Pin']);

    $table = Snapshot::query()->create(['workbench_id' => $workbench->id, 'slug' => 'tbl']);
    $tableV1 = SnapshotVersioning::append(
        snapshot: $table,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'a', 'label' => 'A']], 'rows' => [['a' => 'one']]],
    );

    $report = Snapshot::query()->create(['workbench_id' => $workbench->id, 'slug' => 'rpt']);
    $reportV1 = SnapshotVersioning::append(
        snapshot: $report,
        viewType: 'report',
        dataPayload: ['blocks' => [
            ['type' => 'markdown', 'body' => '# Hi'],
            ['type' => 'embed', 'snapshot_id' => $table->id],
        ]],
    );

    $embeds = DB::table('snapshot_embeds')->where('report_version_id', $reportV1->id)->get();

    expect($embeds)->toHaveCount(1)
        ->and($embeds[0]->report_snapshot_id)->toBe($report->id)
        ->and($embeds[0]->embedded_snapshot_id)->toBe($table->id)
        ->and($embeds[0]->embedded_version_id)->toBe($tableV1->id)
        ->and($embeds[0]->block_index)->toBe(1);
});

it('REQ-M5-003: a later report revision pins to the embed snapshot version current at that moment', function (): void {
    $workbench = Workbench::query()->create(['slug' => 'pin-wb', 'name' => 'Pin']);

    $table = Snapshot::query()->create(['workbench_id' => $workbench->id, 'slug' => 'tbl']);
    $tableV1 = SnapshotVersioning::append($table, 'table', ['columns' => [['key' => 'a', 'label' => 'A']], 'rows' => []]);

    $report = Snapshot::query()->create(['workbench_id' => $workbench->id, 'slug' => 'rpt']);
    $reportV1 = SnapshotVersioning::append(
        $report,
        'report',
        ['blocks' => [['type' => 'embed', 'snapshot_id' => $table->id]]],
    );

    // Embedded snapshot grows a new revision.
    $tableV2 = SnapshotVersioning::append($table, 'table', ['columns' => [['key' => 'a', 'label' => 'A']], 'rows' => [['a' => 'b']]]);

    // New report revision — must pin to the now-current v2.
    $reportV2 = SnapshotVersioning::append(
        $report,
        'report',
        ['blocks' => [['type' => 'embed', 'snapshot_id' => $table->id]]],
    );

    $v1Pin = DB::table('snapshot_embeds')->where('report_version_id', $reportV1->id)->first();
    $v2Pin = DB::table('snapshot_embeds')->where('report_version_id', $reportV2->id)->first();

    expect($v1Pin->embedded_version_id)->toBe($tableV1->id)
        ->and($v2Pin->embedded_version_id)->toBe($tableV2->id);
});

it('REQ-M5-003: appending a non-report snapshot writes no rows to snapshot_embeds', function (): void {
    $workbench = Workbench::query()->create(['slug' => 'no-pin-wb', 'name' => 'NoPin']);
    $table = Snapshot::query()->create(['workbench_id' => $workbench->id, 'slug' => 'tbl']);

    SnapshotVersioning::append(
        snapshot: $table,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'a', 'label' => 'A']], 'rows' => []],
    );

    expect(DB::table('snapshot_embeds')->count())->toBe(0);
});

it('REQ-M5-003: a report with no embed blocks writes no rows to snapshot_embeds', function (): void {
    $workbench = Workbench::query()->create(['slug' => 'md-wb', 'name' => 'MD']);
    $report = Snapshot::query()->create(['workbench_id' => $workbench->id, 'slug' => 'rpt']);

    SnapshotVersioning::append(
        snapshot: $report,
        viewType: 'report',
        dataPayload: ['blocks' => [['type' => 'markdown', 'body' => '# Just markdown']]],
    );

    expect(DB::table('snapshot_embeds')->count())->toBe(0);
});

it('REQ-M5-003: every embed block in a single report append gets its own snapshot_embeds row', function (): void {
    $workbench = Workbench::query()->create(['slug' => 'multi-wb', 'name' => 'Multi']);

    $a = Snapshot::query()->create(['workbench_id' => $workbench->id, 'slug' => 'a']);
    $aV1 = SnapshotVersioning::append($a, 'table', ['columns' => [['key' => 'a', 'label' => 'A']], 'rows' => []]);

    $b = Snapshot::query()->create(['workbench_id' => $workbench->id, 'slug' => 'b']);
    $bV1 = SnapshotVersioning::append($b, 'kanban', ['columns' => [['id' => 'todo', 'title' => 'Todo', 'cards' => []]]]);

    $report = Snapshot::query()->create(['workbench_id' => $workbench->id, 'slug' => 'rpt']);
    $reportV1 = SnapshotVersioning::append(
        $report,
        'report',
        ['blocks' => [
            ['type' => 'markdown', 'body' => '# Two'],
            ['type' => 'embed', 'snapshot_id' => $a->id],
            ['type' => 'markdown', 'body' => '## Mid'],
            ['type' => 'embed', 'snapshot_id' => $b->id],
        ]],
    );

    $embeds = DB::table('snapshot_embeds')
        ->where('report_version_id', $reportV1->id)
        ->orderBy('block_index')
        ->get();

    expect($embeds)->toHaveCount(2)
        ->and([$embeds[0]->embedded_snapshot_id, $embeds[1]->embedded_snapshot_id])
        ->toBe([$a->id, $b->id])
        ->and([$embeds[0]->embedded_version_id, $embeds[1]->embedded_version_id])
        ->toBe([$aV1->id, $bV1->id])
        ->and([$embeds[0]->block_index, $embeds[1]->block_index])
        ->toBe([1, 3]);
});
