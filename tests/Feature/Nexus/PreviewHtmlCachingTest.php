<?php

declare(strict_types=1);

use App\Models\Snapshot;
use App\Models\SnapshotVersion;
use App\Models\Workbench;
use App\Nexus\Renderers\TablePreviewRenderer;
use App\Nexus\SnapshotVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('REQ-M1-012: append() caches preview_html on the row at write-time when not supplied', function (): void {
    $workbench = Workbench::factory()->create();
    $snapshot = Snapshot::factory()->for($workbench)->create();

    $payload = [
        'columns' => [['key' => 'id', 'label' => 'ID']],
        'rows' => [['id' => 1], ['id' => 2]],
    ];

    $version = SnapshotVersioning::append(snapshot: $snapshot, viewType: 'table', dataPayload: $payload);

    $stored = DB::table('snapshot_versions')->where('id', $version->id)->value('preview_html');

    expect($stored)->toBe(TablePreviewRenderer::render($payload));
});

it('REQ-M1-012: explicit previewHtml argument is persisted verbatim', function (): void {
    $workbench = Workbench::factory()->create();
    $snapshot = Snapshot::factory()->for($workbench)->create();

    $version = SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'id']], 'rows' => []],
        previewHtml: '<table data-test="explicit"></table>',
    );

    expect($version->preview_html)->toBe('<table data-test="explicit"></table>');
});

it('REQ-M1-012: subsequent reads return the cached HTML without re-rendering', function (): void {
    $workbench = Workbench::factory()->create();
    $snapshot = Snapshot::factory()->for($workbench)->create();

    $version = SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'id']], 'rows' => [['id' => 1]]],
    );

    // Tamper with the cached value directly. If anything re-rendered on read
    // it would clobber this string back to the original render output.
    $sentinel = '<!-- cached marker -->';
    DB::table('snapshot_versions')->where('id', $version->id)->update(['preview_html' => $sentinel]);

    $reloaded = SnapshotVersion::query()->whereKey($version->id)->first();

    expect($reloaded->preview_html)->toBe($sentinel);
});
