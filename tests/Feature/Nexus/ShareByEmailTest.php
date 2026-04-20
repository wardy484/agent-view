<?php

declare(strict_types=1);

use App\Actions\Fortify\CreateNewUser;
use App\Enums\SnapshotVisibility;
use App\Models\Snapshot;
use App\Models\SnapshotShare;
use App\Models\User;
use App\Models\Workbench;
use App\Nexus\ShareSnapshotByEmail;
use App\Nexus\SnapshotVersioning;
use App\Notifications\SnapshotInvitationNotification;
use App\Notifications\SnapshotSharedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function makeSharedSnapshot(User $owner): Snapshot
{
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snapshot = Snapshot::factory()->for($workbench)->create();
    SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'id']], 'rows' => [['id' => 1]]],
    );
    $snapshot->setVisibility(SnapshotVisibility::Shared);

    return $snapshot->fresh();
}

it('REQ-M4-004: sharing with an existing user prefills user_id and queues the shared notification', function (): void {
    Notification::fake();

    $owner = User::factory()->create();
    $invitee = User::factory()->create(['email' => 'ada@example.com']);
    $snapshot = makeSharedSnapshot($owner);

    $share = app(ShareSnapshotByEmail::class)->share(
        snapshot: $snapshot,
        email: 'Ada@Example.COM',
        grantedBy: $owner,
    );

    expect($share->user_id)->toBe($invitee->id)
        ->and($share->email)->toBe('ada@example.com')
        ->and($share->granted_by_user_id)->toBe($owner->id);

    Notification::assertSentTo($invitee, SnapshotSharedNotification::class);
    Notification::assertNothingSentTo($owner);
});

it('REQ-M4-004: sharing with a stranger leaves user_id null and sends an invitation email', function (): void {
    Notification::fake();

    $owner = User::factory()->create();
    $snapshot = makeSharedSnapshot($owner);

    $share = app(ShareSnapshotByEmail::class)->share(
        snapshot: $snapshot,
        email: 'new@example.com',
        grantedBy: $owner,
    );

    expect($share->user_id)->toBeNull()
        ->and($share->email)->toBe('new@example.com');

    Notification::assertSentOnDemand(SnapshotInvitationNotification::class, function ($notification, $channels, $notifiable) {
        return $notifiable->routes['mail'] === 'new@example.com';
    });
});

it('REQ-M4-004: CreateNewUser backfills user_id on matching unrevoked snapshot_shares rows during registration', function (): void {
    $owner = User::factory()->create();
    $snapshot = makeSharedSnapshot($owner);

    SnapshotShare::query()->create([
        'snapshot_id' => $snapshot->id,
        'email' => 'late@example.com',
        'user_id' => null,
        'granted_by_user_id' => $owner->id,
    ]);

    $user = app(CreateNewUser::class)->create([
        'name' => 'Late Comer',
        'email' => 'LATE@example.com',
        'password' => 'Password!9',
        'password_confirmation' => 'Password!9',
        'terms' => '1',
    ]);

    $share = SnapshotShare::query()
        ->where('snapshot_id', $snapshot->id)
        ->where('email', 'late@example.com')
        ->first();

    expect($share->user_id)->toBe($user->id);
});

it('REQ-M4-004: CreateNewUser does not backfill revoked share rows', function (): void {
    $owner = User::factory()->create();
    $snapshot = makeSharedSnapshot($owner);

    $share = SnapshotShare::query()->create([
        'snapshot_id' => $snapshot->id,
        'email' => 'revoked@example.com',
        'user_id' => null,
        'granted_by_user_id' => $owner->id,
        'revoked_at' => now(),
    ]);

    app(CreateNewUser::class)->create([
        'name' => 'Revoked Person',
        'email' => 'revoked@example.com',
        'password' => 'Password!9',
        'password_confirmation' => 'Password!9',
        'terms' => '1',
    ]);

    expect($share->fresh()->user_id)->toBeNull();
});

it('REQ-M4-004: re-inviting a revoked email creates a fresh active row rather than resurrecting the revoked one', function (): void {
    $owner = User::factory()->create();
    $invitee = User::factory()->create(['email' => 'cycle@example.com']);
    $snapshot = makeSharedSnapshot($owner);

    $original = app(ShareSnapshotByEmail::class)->share(
        snapshot: $snapshot,
        email: 'cycle@example.com',
        grantedBy: $owner,
    );
    $original->revoke();

    $refreshed = app(ShareSnapshotByEmail::class)->share(
        snapshot: $snapshot,
        email: 'cycle@example.com',
        grantedBy: $owner,
    );

    expect($refreshed->id)->not->toBe($original->id)
        ->and($refreshed->user_id)->toBe($invitee->id)
        ->and($refreshed->isActive())->toBeTrue()
        ->and($original->fresh()->revoked_at)->not->toBeNull();
});
