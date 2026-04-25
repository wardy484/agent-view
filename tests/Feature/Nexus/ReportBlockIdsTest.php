<?php

declare(strict_types=1);

use App\Models\Snapshot;
use App\Models\Workbench;
use App\Nexus\Schemas\ReportViewSchema;
use App\Nexus\Schemas\ReportViewSchemaException;
use App\Nexus\SnapshotVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('REQ-M6-001: ReportViewSchema accepts explicit UUID block ids and returns them unchanged', function (): void {
    $idA = Str::uuid()->toString();
    $idB = Str::uuid()->toString();

    $payload = [
        'blocks' => [
            ['id' => $idA, 'type' => 'markdown', 'body' => '# Title'],
            ['id' => $idB, 'type' => 'markdown', 'body' => '## Body'],
        ],
    ];

    $result = ReportViewSchema::validate($payload);

    expect($result['blocks'][0]['id'])->toBe($idA)
        ->and($result['blocks'][1]['id'])->toBe($idB);
});

it('REQ-M6-001: ReportViewSchema auto-assigns UUIDs to blocks missing ids', function (): void {
    $payload = [
        'blocks' => [
            ['type' => 'markdown', 'body' => '# A'],
            ['type' => 'markdown', 'body' => '## B'],
        ],
    ];

    $result = ReportViewSchema::validate($payload);

    expect($result['blocks'][0])->toHaveKey('id')
        ->and($result['blocks'][1])->toHaveKey('id')
        ->and(Str::isUuid($result['blocks'][0]['id']))->toBeTrue()
        ->and(Str::isUuid($result['blocks'][1]['id']))->toBeTrue()
        ->and($result['blocks'][0]['id'])->not->toBe($result['blocks'][1]['id']);
});

it('REQ-M6-001: ReportViewSchema rejects non-UUID id values with dot-path error', function (): void {
    expect(fn () => ReportViewSchema::validate([
        'blocks' => [
            ['id' => 'not-a-uuid', 'type' => 'markdown', 'body' => '# X'],
        ],
    ]))->toThrow(
        ReportViewSchemaException::class,
        'data_payload.blocks[0].id must be a UUID string when supplied.',
    );
});

it('REQ-M6-001: ReportViewSchema carries forward previous version block id when markdown body sha1 matches', function (): void {
    $stableId = Str::uuid()->toString();

    $previousBlocks = [
        ['id' => $stableId, 'type' => 'markdown', 'body' => '# Carry me'],
        ['id' => Str::uuid()->toString(), 'type' => 'markdown', 'body' => '## Other'],
    ];

    $payload = [
        'blocks' => [
            ['type' => 'markdown', 'body' => '# Brand new heading'],
            ['type' => 'markdown', 'body' => '# Carry me'],
        ],
    ];

    $result = ReportViewSchema::validate($payload, null, $previousBlocks);

    expect($result['blocks'][1]['id'])->toBe($stableId)
        ->and($result['blocks'][0]['id'])->not->toBe($stableId)
        ->and(Str::isUuid($result['blocks'][0]['id']))->toBeTrue();
});

it('REQ-M6-001: ReportViewSchema carries forward previous version block id when embed snapshot_id matches', function (): void {
    $workbench = Workbench::query()->create(['slug' => 'carry-wb', 'name' => 'Carry']);
    $target = Snapshot::query()->create(['workbench_id' => $workbench->id, 'slug' => 'tgt']);
    SnapshotVersioning::append(
        snapshot: $target,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'a', 'label' => 'A']], 'rows' => []],
    );

    $stableEmbedId = Str::uuid()->toString();

    $previousBlocks = [
        ['id' => $stableEmbedId, 'type' => 'embed', 'snapshot_id' => $target->id],
    ];

    $payload = [
        'blocks' => [
            ['type' => 'markdown', 'body' => '## Wrap-up'],
            ['type' => 'embed', 'snapshot_id' => $target->id],
        ],
    ];

    $result = ReportViewSchema::validate($payload, $workbench->id, $previousBlocks);

    expect($result['blocks'][1]['id'])->toBe($stableEmbedId);
});

it('REQ-M6-001: ReportViewSchema generates fresh UUID when no prior block matches by content', function (): void {
    $previousBlocks = [
        ['id' => Str::uuid()->toString(), 'type' => 'markdown', 'body' => '# Old'],
    ];

    $payload = [
        'blocks' => [
            ['type' => 'markdown', 'body' => '# Different content entirely'],
        ],
    ];

    $result = ReportViewSchema::validate($payload, null, $previousBlocks);

    expect(Str::isUuid($result['blocks'][0]['id']))->toBeTrue()
        ->and($result['blocks'][0]['id'])->not->toBe($previousBlocks[0]['id']);
});

it('REQ-M6-001: SnapshotVersioning::append wires previous-version blocks into the report validator on subsequent revisions', function (): void {
    $workbench = Workbench::query()->create(['slug' => 'append-wb', 'name' => 'Append']);
    $report = Snapshot::query()->create(['workbench_id' => $workbench->id, 'slug' => 'rpt']);

    $v1 = SnapshotVersioning::append(
        snapshot: $report,
        viewType: 'report',
        dataPayload: [
            'blocks' => [
                ['type' => 'markdown', 'body' => '# Stable heading'],
                ['type' => 'markdown', 'body' => '## Will change'],
            ],
        ],
    );

    $v1Blocks = $v1->data_payload['blocks'];
    $stableId = $v1Blocks[0]['id'];
    $changingId = $v1Blocks[1]['id'];

    expect(Str::isUuid($stableId))->toBeTrue()
        ->and(Str::isUuid($changingId))->toBeTrue();

    // Second revision: keep the first block's body verbatim (no id supplied),
    // rewrite the second block's body. Carry-forward should preserve the
    // first block's id; the second block should get a fresh UUID.
    $v2 = SnapshotVersioning::append(
        snapshot: $report,
        viewType: 'report',
        dataPayload: [
            'blocks' => [
                ['type' => 'markdown', 'body' => '# Stable heading'],
                ['type' => 'markdown', 'body' => '## Completely rewritten'],
            ],
        ],
    );

    $v2Blocks = $v2->data_payload['blocks'];

    expect($v2Blocks[0]['id'])->toBe($stableId)
        ->and($v2Blocks[1]['id'])->not->toBe($changingId)
        ->and(Str::isUuid($v2Blocks[1]['id']))->toBeTrue();
});
