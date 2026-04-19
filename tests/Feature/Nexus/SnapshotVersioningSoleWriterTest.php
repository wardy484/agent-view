<?php

declare(strict_types=1);

use App\Models\Snapshot;
use App\Models\SnapshotVersion;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use App\Nexus\SnapshotVersioningException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('REQ-M1-009: direct SnapshotVersion::create() throws', function (): void {
    $workbench = Workbench::factory()->create();
    $snapshot = Snapshot::factory()->for($workbench)->create();

    expect(fn () => SnapshotVersion::create([
        'snapshot_id' => $snapshot->id,
        'revision' => 1,
        'view_type' => 'table',
        'data_payload' => ['columns' => [['key' => 'id']], 'rows' => []],
    ]))->toThrow(SnapshotVersioningException::class);
});

it('REQ-M1-009: direct ->save() on a new SnapshotVersion throws', function (): void {
    $workbench = Workbench::factory()->create();
    $snapshot = Snapshot::factory()->for($workbench)->create();

    $version = new SnapshotVersion([
        'snapshot_id' => $snapshot->id,
        'revision' => 1,
        'view_type' => 'table',
        'data_payload' => ['columns' => [['key' => 'id']], 'rows' => []],
    ]);

    expect(fn () => $version->save())->toThrow(SnapshotVersioningException::class);
});

it('REQ-M1-009: SnapshotVersioning::append() is allowed and persists the row', function (): void {
    $workbench = Workbench::factory()->create();
    $snapshot = Snapshot::factory()->for($workbench)->create();

    $version = SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'id']], 'rows' => [['id' => 1]]],
    );

    expect($version->exists)->toBeTrue()
        ->and($version->revision)->toBe(1);
});

it('REQ-M1-009: no app class outside App\\Nexus writes to snapshot_versions', function (): void {
    $appPath = app_path();
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appPath));
    $offenders = [];

    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $relative = str_replace($appPath.DIRECTORY_SEPARATOR, '', $file->getPathname());

        // The sole authorised writer lives here.
        if (str_starts_with($relative, 'Nexus'.DIRECTORY_SEPARATOR.'SnapshotVersioning')) {
            continue;
        }

        // The model file itself defines the guard hook.
        if (str_starts_with($relative, 'Models'.DIRECTORY_SEPARATOR.'SnapshotVersion.php')) {
            continue;
        }

        $contents = file_get_contents($file->getPathname()) ?: '';

        if (preg_match('/SnapshotVersion::(create|insert|update|forceCreate|updateOrCreate|firstOrCreate)\b/', $contents)) {
            $offenders[] = $relative;
        }

        if (preg_match('/new\s+SnapshotVersion\b[^;]*->\s*save\b/s', $contents)) {
            $offenders[] = $relative;
        }
    }

    expect($offenders)->toBe([], 'These files write to SnapshotVersion outside SnapshotVersioning: '.implode(', ', $offenders));
});
