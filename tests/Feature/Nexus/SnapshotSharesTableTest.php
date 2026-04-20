<?php

declare(strict_types=1);

use App\Models\Snapshot;
use App\Models\SnapshotShare;
use App\Models\User;
use App\Models\Workbench;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('REQ-M4-003: snapshot_shares table exists with expected columns', function (): void {
    $schema = SnapshotShare::query()->getConnection()->getSchemaBuilder();

    expect($schema->hasTable('snapshot_shares'))->toBeTrue();
    foreach (['id', 'snapshot_id', 'email', 'user_id', 'granted_by_user_id', 'created_at', 'revoked_at'] as $column) {
        expect($schema->hasColumn('snapshot_shares', $column))->toBeTrue("missing column {$column}");
    }
});

it('REQ-M4-003: email is lowercased when a share is created', function (): void {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snapshot = Snapshot::factory()->for($workbench)->create();

    $share = SnapshotShare::query()->create([
        'snapshot_id' => $snapshot->id,
        'email' => 'ADA@Example.COM',
        'granted_by_user_id' => $owner->id,
    ]);

    expect($share->fresh()->email)->toBe('ada@example.com');
});

it('REQ-M4-003: relations resolve snapshot, user, grantedBy', function (): void {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snapshot = Snapshot::factory()->for($workbench)->create();

    $share = SnapshotShare::query()->create([
        'snapshot_id' => $snapshot->id,
        'email' => $invitee->email,
        'user_id' => $invitee->id,
        'granted_by_user_id' => $owner->id,
    ]);

    expect($share->snapshot->id)->toBe($snapshot->id)
        ->and($share->user->id)->toBe($invitee->id)
        ->and($share->grantedBy->id)->toBe($owner->id);
});

it('REQ-M4-003: cannot have two unrevoked shares for the same (snapshot, email)', function (): void {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snapshot = Snapshot::factory()->for($workbench)->create();

    SnapshotShare::query()->create([
        'snapshot_id' => $snapshot->id,
        'email' => 'ada@example.com',
        'granted_by_user_id' => $owner->id,
    ]);

    expect(fn () => SnapshotShare::query()->create([
        'snapshot_id' => $snapshot->id,
        'email' => 'ada@example.com',
        'granted_by_user_id' => $owner->id,
    ]))->toThrow(QueryException::class);
});

it('REQ-M4-003: a revoked share does not block re-sharing the same email', function (): void {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snapshot = Snapshot::factory()->for($workbench)->create();

    $revoked = SnapshotShare::query()->create([
        'snapshot_id' => $snapshot->id,
        'email' => 'ada@example.com',
        'granted_by_user_id' => $owner->id,
        'revoked_at' => now(),
    ]);

    // Should succeed — partial unique index ignores revoked rows.
    $fresh = SnapshotShare::query()->create([
        'snapshot_id' => $snapshot->id,
        'email' => 'ada@example.com',
        'granted_by_user_id' => $owner->id,
    ]);

    expect($fresh->id)->not->toBe($revoked->id)
        ->and($fresh->revoked_at)->toBeNull();
});

it('REQ-M4-003: soft revoke sets revoked_at instead of deleting', function (): void {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snapshot = Snapshot::factory()->for($workbench)->create();

    $share = SnapshotShare::factory()
        ->for($snapshot)
        ->create(['granted_by_user_id' => $owner->id]);

    $share->revoke();

    expect($share->fresh()->revoked_at)->not->toBeNull()
        ->and(SnapshotShare::query()->whereKey($share->id)->exists())->toBeTrue();
});

it('REQ-M4-003: snapshot->shares returns only non-revoked rows by default', function (): void {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snapshot = Snapshot::factory()->for($workbench)->create();

    SnapshotShare::query()->create([
        'snapshot_id' => $snapshot->id,
        'email' => 'live@example.com',
        'granted_by_user_id' => $owner->id,
    ]);
    SnapshotShare::query()->create([
        'snapshot_id' => $snapshot->id,
        'email' => 'gone@example.com',
        'granted_by_user_id' => $owner->id,
        'revoked_at' => now(),
    ]);

    $emails = $snapshot->shares()->pluck('email')->all();

    expect($emails)->toEqualCanonicalizing(['live@example.com']);
});
