<?php

declare(strict_types=1);

use App\Models\Snapshot;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('REQ-M6-002: snapshot_embeds table has block_id column and no block_index column after migration', function (): void {
    expect(Schema::hasColumn('snapshot_embeds', 'block_id'))->toBeTrue();
    expect(Schema::hasColumn('snapshot_embeds', 'block_index'))->toBeFalse();
});

it('REQ-M6-002: SnapshotVersioning writes snapshot_embeds rows keyed by block_id from the validated payload', function (): void {
    $workbench = Workbench::query()->create(['slug' => 'bid-wb', 'name' => 'BlockId']);

    $table = Snapshot::query()->create(['workbench_id' => $workbench->id, 'slug' => 'tbl']);
    SnapshotVersioning::append(
        snapshot: $table,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'a', 'label' => 'A']], 'rows' => []],
    );

    $report = Snapshot::query()->create(['workbench_id' => $workbench->id, 'slug' => 'rpt']);
    $reportV1 = SnapshotVersioning::append(
        snapshot: $report,
        viewType: 'report',
        dataPayload: ['blocks' => [
            ['type' => 'markdown', 'body' => '# Heading'],
            ['type' => 'embed', 'snapshot_id' => $table->id],
        ]],
    );

    $row = DB::table('snapshot_embeds')
        ->where('report_version_id', $reportV1->id)
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->block_id)->not->toBeNull()
        ->and(Str::isUuid($row->block_id))->toBeTrue();

    $blocks = $reportV1->refresh()->data_payload['blocks'];
    $embedBlock = collect($blocks)->firstWhere('type', 'embed');

    expect($row->block_id)->toBe($embedBlock['id']);
});

it('REQ-M6-002: re-appending a report version reuses the same block_id when content carries forward', function (): void {
    $workbench = Workbench::query()->create(['slug' => 'carry-wb', 'name' => 'Carry']);

    $table = Snapshot::query()->create(['workbench_id' => $workbench->id, 'slug' => 'tbl']);
    SnapshotVersioning::append(
        snapshot: $table,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'a', 'label' => 'A']], 'rows' => []],
    );

    $report = Snapshot::query()->create(['workbench_id' => $workbench->id, 'slug' => 'rpt']);

    $reportV1 = SnapshotVersioning::append(
        $report,
        'report',
        ['blocks' => [
            ['type' => 'markdown', 'body' => '# Hi'],
            ['type' => 'embed', 'snapshot_id' => $table->id],
        ]],
    );

    $reportV2 = SnapshotVersioning::append(
        $report,
        'report',
        ['blocks' => [
            ['type' => 'markdown', 'body' => '# Hi'],
            ['type' => 'embed', 'snapshot_id' => $table->id],
            ['type' => 'markdown', 'body' => '## Trailing addition'],
        ]],
    );

    $v1Embed = DB::table('snapshot_embeds')->where('report_version_id', $reportV1->id)->first();
    $v2Embed = DB::table('snapshot_embeds')->where('report_version_id', $reportV2->id)->first();

    expect($v1Embed->block_id)->toBe($v2Embed->block_id);
});

it('REQ-M6-002: snapshot_embeds.block_id matches the id of the corresponding embed block in data_payload', function (): void {
    $workbench = Workbench::query()->create(['slug' => 'match-wb', 'name' => 'Match']);

    $a = Snapshot::query()->create(['workbench_id' => $workbench->id, 'slug' => 'a']);
    SnapshotVersioning::append($a, 'table', ['columns' => [['key' => 'a', 'label' => 'A']], 'rows' => []]);

    $b = Snapshot::query()->create(['workbench_id' => $workbench->id, 'slug' => 'b']);
    SnapshotVersioning::append($b, 'kanban', ['columns' => [['id' => 'todo', 'title' => 'Todo', 'cards' => []]]]);

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

    $blocks = $reportV1->refresh()->data_payload['blocks'];
    $expectedIds = collect($blocks)
        ->where('type', 'embed')
        ->pluck('id')
        ->all();

    $embedBlockIds = DB::table('snapshot_embeds')
        ->where('report_version_id', $reportV1->id)
        ->orderBy('id')
        ->pluck('block_id')
        ->all();

    expect(count($embedBlockIds))->toBe(2);
    sort($expectedIds);
    sort($embedBlockIds);
    expect($embedBlockIds)->toBe($expectedIds);
});
