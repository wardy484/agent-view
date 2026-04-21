<?php

declare(strict_types=1);

use App\Models\Snapshot;
use App\Models\User;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use App\Nexus\WorkbenchActivityBackfill;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('REQ-M6-000: workbenches gain four nullable organisational timestamp columns', function (): void {
    $schema = Schema::getConnection()->getSchemaBuilder();

    foreach (['pinned_at', 'archived_at', 'deleted_at', 'last_activity_at'] as $column) {
        expect($schema->hasColumn('workbenches', $column))
            ->toBeTrue("workbenches.{$column} should exist");
    }

    // Every new column must accept null.
    $workbench = Workbench::factory()->create([
        'pinned_at' => null,
        'archived_at' => null,
        'last_activity_at' => null,
    ]);

    expect($workbench->pinned_at)->toBeNull()
        ->and($workbench->archived_at)->toBeNull()
        ->and($workbench->last_activity_at)->toBeNull()
        ->and($workbench->deleted_at)->toBeNull();
});

it('REQ-M6-000: Workbench model uses the SoftDeletes trait', function (): void {
    expect(in_array(SoftDeletes::class, class_uses_recursive(Workbench::class), true))
        ->toBeTrue();

    $workbench = Workbench::factory()->create();
    $workbench->delete();

    // Default queries exclude soft-deleted rows.
    expect(Workbench::query()->whereKey($workbench->id)->exists())->toBeFalse();
    // But they are still present when the scope is removed.
    expect(Workbench::withTrashed()->whereKey($workbench->id)->exists())->toBeTrue();
});

it('REQ-M6-000: new columns cast to Carbon date instances', function (): void {
    $workbench = Workbench::factory()->create([
        'pinned_at' => now(),
        'archived_at' => now(),
        'last_activity_at' => now(),
    ]);

    expect($workbench->pinned_at)->toBeInstanceOf(CarbonInterface::class)
        ->and($workbench->archived_at)->toBeInstanceOf(CarbonInterface::class)
        ->and($workbench->last_activity_at)->toBeInstanceOf(CarbonInterface::class);
});

it('REQ-M6-000: indexes exist for owner-scoped organisational queries', function (): void {
    $indexes = collect(DB::select(
        "select indexname from pg_indexes where tablename = 'workbenches'"
    ))->pluck('indexname')->all();

    $expectedColumns = [
        ['owner_user_id', 'pinned_at'],
        ['owner_user_id', 'archived_at'],
        ['owner_user_id', 'last_activity_at'],
    ];

    foreach ($expectedColumns as $cols) {
        $match = collect($indexes)->first(function (string $name) use ($cols): bool {
            $def = DB::selectOne(
                'select indexdef from pg_indexes where indexname = ?',
                [$name]
            );

            if ($def === null) {
                return false;
            }

            foreach ($cols as $column) {
                if (! str_contains($def->indexdef, $column)) {
                    return false;
                }
            }

            return true;
        });

        expect($match)->not->toBeNull(
            'Expected composite index over ('.implode(', ', $cols).') on workbenches'
        );
    }
});

it('REQ-M6-000: last_activity_at back-fills from most recent snapshot_versions.created_at', function (): void {
    // Simulate the pre-migration state by clearing the column after the
    // migration has already set it, then running the backfill in isolation.
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snapshot = Snapshot::factory()->for($workbench)->create();

    SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'id']], 'rows' => [['id' => 1]]],
    );
    // Rewind the first version into the past so it is older than the second.
    DB::table('snapshot_versions')
        ->where('snapshot_id', $snapshot->id)
        ->update(['created_at' => now()->subDays(5), 'updated_at' => now()->subDays(5)]);

    SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'id']], 'rows' => [['id' => 2]]],
    );
    $latestVersionCreatedAt = now()->subDay();
    DB::table('snapshot_versions')
        ->where('snapshot_id', $snapshot->id)
        ->where('revision', 2)
        ->update(['created_at' => $latestVersionCreatedAt, 'updated_at' => $latestVersionCreatedAt]);

    DB::table('workbenches')->where('id', $workbench->id)->update(['last_activity_at' => null]);

    WorkbenchActivityBackfill::run();

    $workbench->refresh();

    expect($workbench->last_activity_at)->not->toBeNull()
        ->and($workbench->last_activity_at->timestamp)
        ->toBe($latestVersionCreatedAt->timestamp);
});

it('REQ-M6-000: last_activity_at back-fills to workbenches.created_at when no versions exist', function (): void {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create([
        'owner_user_id' => $owner->id,
        'created_at' => now()->subDays(10),
    ]);

    DB::table('workbenches')->where('id', $workbench->id)->update(['last_activity_at' => null]);

    WorkbenchActivityBackfill::run();

    $workbench->refresh();

    expect($workbench->last_activity_at)->not->toBeNull()
        ->and($workbench->last_activity_at->timestamp)
        ->toBe($workbench->created_at->timestamp);
});
