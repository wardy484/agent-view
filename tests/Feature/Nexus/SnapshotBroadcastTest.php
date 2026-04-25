<?php

declare(strict_types=1);

use App\Enums\SnapshotVisibility;
use App\Events\SnapshotVersionAppended;
use App\Models\Snapshot;
use App\Models\SnapshotShare;
use App\Models\User;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

/**
 * Resolve the channel-auth callback registered in routes/channels.php for a
 * given concrete channel name. Returns null when no matching callback exists.
 *
 * Reverb stores channels as `{prefix}.{wildcard}`-style strings; we match by
 * regex placeholder substitution to mirror Laravel's runtime resolver.
 */
function resolveSnapshotChannelCallback(int $snapshotId): callable
{
    $broadcaster = Broadcast::driver('null');
    // The closure is stored on the abstract Broadcaster — accessing it via
    // reflection keeps the test independent of the active driver.
    $reflected = new ReflectionClass($broadcaster);
    $property = $reflected->getProperty('channels');
    $callbacks = $property->getValue($broadcaster);

    foreach ($callbacks as $pattern => $callback) {
        // Pattern is e.g. `snapshot.{snapshotId}` — replace the placeholder
        // with a sentinel before quoting, then swap the sentinel back to the
        // capture group. Avoids preg_quote escaping the braces themselves.
        $sentinel = '__WILDCARD__';
        $withSentinel = preg_replace('/\{[^}]+\}/', $sentinel, $pattern);
        $regex = str_replace($sentinel, '([^.]+)', preg_quote($withSentinel, '/'));
        if (preg_match('/^'.$regex.'$/', 'snapshot.'.$snapshotId)) {
            return $callback;
        }
    }

    throw new RuntimeException('No channel callback registered for snapshot.'.$snapshotId);
}

function makeSnapshot(User $owner, SnapshotVisibility $visibility = SnapshotVisibility::Private): Snapshot
{
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snapshot = Snapshot::factory()->for($workbench)->create();
    SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'id']], 'rows' => [['id' => 1]]],
    );
    if ($visibility !== SnapshotVisibility::Private) {
        $snapshot->setVisibility($visibility);
    }

    return $snapshot->fresh();
}

it('REQ-M7-002: SnapshotVersioning::append broadcasts SnapshotVersionAppended on the private snapshot channel', function (): void {
    Event::fake([SnapshotVersionAppended::class]);

    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snapshot = Snapshot::factory()->for($workbench)->create();

    $version = SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'id']], 'rows' => [['id' => 1]]],
    );

    Event::assertDispatched(
        SnapshotVersionAppended::class,
        function (SnapshotVersionAppended $event) use ($snapshot, $version): bool {
            $channels = $event->broadcastOn();
            $channel = is_array($channels) ? $channels[0] : $channels;

            return $channel instanceof PrivateChannel
                && $channel->name === 'private-snapshot.'.$snapshot->id
                && $event->snapshotId === (int) $snapshot->id
                && $event->versionId === (int) $version->id
                && $event->revision === 1;
        }
    );
});

it('REQ-M7-002: broadcast payload contains snapshot_id, version_id, revision, view_type, summary and NOT data_payload', function (): void {
    Event::fake([SnapshotVersionAppended::class]);

    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snapshot = Snapshot::factory()->for($workbench)->create();

    SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'id']], 'rows' => [['id' => 42]]],
        metadata: ['summary' => 'first revision'],
    );

    Event::assertDispatched(
        SnapshotVersionAppended::class,
        function (SnapshotVersionAppended $event) use ($snapshot): bool {
            $payload = $event->broadcastWith();

            expect(array_keys($payload))->toEqualCanonicalizing([
                'snapshot_id', 'version_id', 'revision', 'view_type', 'summary',
            ]);
            expect($payload)->not->toHaveKey('data_payload');
            expect($payload['snapshot_id'])->toBe((int) $snapshot->id);
            expect($payload['view_type'])->toBe('table');
            expect($payload['revision'])->toBe(1);
            expect($payload['summary'])->toBe('first revision');

            return true;
        }
    );
});

it('REQ-M7-002: broadcast omits the summary key entirely when metadata.summary is absent', function (): void {
    Event::fake([SnapshotVersionAppended::class]);

    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snapshot = Snapshot::factory()->for($workbench)->create();

    SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'id']], 'rows' => [['id' => 1]]],
    );

    Event::assertDispatched(
        SnapshotVersionAppended::class,
        function (SnapshotVersionAppended $event): bool {
            expect($event->broadcastWith())->not->toHaveKey('summary');
            expect($event->broadcastWith())->not->toHaveKey('data_payload');

            return true;
        }
    );
});

it('REQ-M7-002: channel auth grants the snapshot owner', function (): void {
    $owner = User::factory()->create();
    $snapshot = makeSnapshot($owner);

    $callback = resolveSnapshotChannelCallback($snapshot->id);

    expect($callback($owner, $snapshot->id))->toBeTrue();
});

it('REQ-M7-002: channel auth grants an active snapshot_shares grantee but denies a revoked grantee', function (): void {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $snapshot = makeSnapshot($owner, SnapshotVisibility::Shared);

    $share = SnapshotShare::factory()
        ->for($snapshot)
        ->forUser($invitee)
        ->create(['granted_by_user_id' => $owner->id]);

    $callback = resolveSnapshotChannelCallback($snapshot->id);

    expect($callback($invitee, $snapshot->id))->toBeTrue();

    // Now revoke the share — channel auth must flip to false.
    $share->revoke();

    expect($callback($invitee->fresh(), $snapshot->id))->toBeFalse();
});

it('REQ-M7-002: channel auth denies an unrelated authenticated user', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $snapshot = makeSnapshot($owner);

    $callback = resolveSnapshotChannelCallback($snapshot->id);

    expect($callback($stranger, $snapshot->id))->toBeFalse();
});

it('REQ-M7-002: channel auth denies a link-token viewer (no token path on private channels)', function (): void {
    // Link-shared snapshots may be VIEWED via the share token over HTTP, but
    // private websocket subscriptions must not honour the token because the
    // channel-auth contract has no place to surface it. Confirm a stranger
    // gets `false` even when the snapshot is link-shared.
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $snapshot = makeSnapshot($owner, SnapshotVisibility::Link);

    $callback = resolveSnapshotChannelCallback($snapshot->id);

    expect($callback($stranger, $snapshot->id))->toBeFalse();
});
