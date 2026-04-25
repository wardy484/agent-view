<?php

declare(strict_types=1);

use App\Models\Snapshot;
use App\Models\Workbench;
use App\Nexus\Comments\BackfillBlockIds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Insert a snapshot_versions row directly via DB::table(), bypassing the
 * SnapshotVersioning::append() validator + saving guard so we can simulate
 * historical rows whose payload pre-dates REQ-M6-001 (i.e. blocks lacking
 * the `id` field).
 *
 * @param  array<string, mixed>  $payload
 */
function insertHistoricalSnapshotVersion(string $viewType, array $payload, int $revision = 1): array
{
    $workbench = Workbench::factory()->create();
    $snapshot = Snapshot::factory()->for($workbench)->create();

    $now = now();

    $id = DB::table('snapshot_versions')->insertGetId([
        'snapshot_id' => $snapshot->id,
        'revision' => $revision,
        'view_type' => $viewType,
        'data_payload' => json_encode($payload),
        'metadata' => null,
        'preview_html' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return ['snapshot' => $snapshot, 'version_id' => $id];
}

function loadVersionPayload(int $versionId): array
{
    $row = DB::table('snapshot_versions')->where('id', $versionId)->first(['data_payload']);

    return is_string($row->data_payload)
        ? json_decode($row->data_payload, true)
        : (array) $row->data_payload;
}

it('REQ-M6-025: assigns UUIDs to blocks lacking id on existing report versions', function (): void {
    ['version_id' => $versionId] = insertHistoricalSnapshotVersion('report', [
        'blocks' => [
            ['type' => 'markdown', 'body' => 'First paragraph.'],
            ['type' => 'markdown', 'body' => 'Second paragraph.'],
        ],
    ]);

    $updated = BackfillBlockIds::run();

    expect($updated)->toBe(1);

    $payload = loadVersionPayload($versionId);

    expect($payload['blocks'])->toHaveCount(2);

    foreach ($payload['blocks'] as $block) {
        expect($block)->toHaveKey('id');
        expect(Str::isUuid($block['id']))->toBeTrue();
    }

    // The two ids must differ — each block gets its own UUID.
    expect($payload['blocks'][0]['id'])->not->toBe($payload['blocks'][1]['id']);
});

it('REQ-M6-025: leaves blocks that already carry ids untouched', function (): void {
    $existingId = Str::uuid()->toString();

    ['version_id' => $versionId] = insertHistoricalSnapshotVersion('report', [
        'blocks' => [
            ['id' => $existingId, 'type' => 'markdown', 'body' => 'Already-tagged.'],
        ],
    ]);

    $updated = BackfillBlockIds::run();

    expect($updated)->toBe(0);

    $payload = loadVersionPayload($versionId);

    expect($payload['blocks'][0]['id'])->toBe($existingId);
});

it('REQ-M6-025: skips non-report snapshot_versions entirely', function (): void {
    $tablePayload = [
        'columns' => [
            ['key' => 'name', 'label' => 'Name'],
        ],
        'rows' => [
            ['name' => 'Alice'],
        ],
    ];

    ['version_id' => $versionId] = insertHistoricalSnapshotVersion('table', $tablePayload);

    $beforeBytes = DB::table('snapshot_versions')->where('id', $versionId)->value('data_payload');

    $updated = BackfillBlockIds::run();

    expect($updated)->toBe(0);

    $afterBytes = DB::table('snapshot_versions')->where('id', $versionId)->value('data_payload');

    expect($afterBytes)->toBe($beforeBytes);
});

it('REQ-M6-025: is idempotent (running twice produces no changes after the first)', function (): void {
    ['version_id' => $versionId] = insertHistoricalSnapshotVersion('report', [
        'blocks' => [
            ['type' => 'markdown', 'body' => 'Para A.'],
            ['type' => 'markdown', 'body' => 'Para B.'],
            ['type' => 'markdown', 'body' => 'Para C.'],
        ],
    ]);

    $firstUpdated = BackfillBlockIds::run();
    expect($firstUpdated)->toBe(1);

    $payloadAfterFirst = loadVersionPayload($versionId);
    $idsAfterFirst = array_column($payloadAfterFirst['blocks'], 'id');

    $secondUpdated = BackfillBlockIds::run();
    expect($secondUpdated)->toBe(0);

    $payloadAfterSecond = loadVersionPayload($versionId);
    $idsAfterSecond = array_column($payloadAfterSecond['blocks'], 'id');

    expect($idsAfterSecond)->toBe($idsAfterFirst);
});
