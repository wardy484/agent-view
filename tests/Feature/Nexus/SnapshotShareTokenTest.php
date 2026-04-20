<?php

declare(strict_types=1);

use App\Enums\SnapshotVisibility;
use App\Models\Snapshot;
use App\Models\SnapshotShareAccess;
use App\Models\User;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

/**
 * Set up a link-shareable snapshot with one rendered revision. Returns the
 * (fresh) snapshot so tests can read its share_token.
 */
function linkShareableSnapshot(): Snapshot
{
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snapshot = Snapshot::factory()->for($workbench)->create();

    SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'id', 'label' => 'ID']], 'rows' => [['id' => 1]]],
    );

    $snapshot->setVisibility(SnapshotVisibility::Link);

    return $snapshot->fresh();
}

it('REQ-M4-002: setVisibility(Link) from Private mints a 32-byte URL-safe token', function (): void {
    $snapshot = Snapshot::factory()->for(Workbench::factory())->create();

    expect($snapshot->share_token)->toBeNull();

    $snapshot->setVisibility(SnapshotVisibility::Link);
    $fresh = $snapshot->fresh();

    expect($fresh->visibility)->toBe(SnapshotVisibility::Link)
        ->and($fresh->share_token)->toBeString()
        ->and(strlen((string) $fresh->share_token))->toBeGreaterThanOrEqual(32)
        ->and(preg_match('/^[A-Za-z0-9_-]+$/', (string) $fresh->share_token))->toBe(1);
});

it('REQ-M4-002: setVisibility(Private) clears the share_token', function (): void {
    $snapshot = linkShareableSnapshot();

    expect($snapshot->share_token)->not->toBeNull();

    $snapshot->setVisibility(SnapshotVisibility::Private);

    expect($snapshot->fresh()->share_token)->toBeNull();
});

it('REQ-M4-002: Private → Link after a prior Link mints a fresh token', function (): void {
    $snapshot = linkShareableSnapshot();
    $original = $snapshot->share_token;

    $snapshot->setVisibility(SnapshotVisibility::Private);
    $snapshot->setVisibility(SnapshotVisibility::Link);

    expect($snapshot->fresh()->share_token)->not->toBeNull()
        ->and($snapshot->fresh()->share_token)->not->toBe($original);
});

it('REQ-M4-002: setVisibility(Shared) retires the link token', function (): void {
    $snapshot = linkShareableSnapshot();

    expect($snapshot->share_token)->not->toBeNull();

    $snapshot->setVisibility(SnapshotVisibility::Shared);

    expect($snapshot->fresh()->share_token)->toBeNull();
});

it('REQ-M4-002: GET /s/{token} resolves the snapshot for an active link token', function (): void {
    $snapshot = linkShareableSnapshot();

    $response = $this->get('/s/'.$snapshot->share_token);

    $response->assertOk();
});

it('REQ-M4-002: GET /s/{token} returns 404 for an unknown token', function (): void {
    $this->get('/s/this-token-does-not-exist')->assertNotFound();
});

it('REQ-M4-002: GET /s/{token} returns 404 when visibility is not link', function (): void {
    $snapshot = linkShareableSnapshot();
    $token = $snapshot->share_token;

    // Flip off link sharing but keep the token referenced below: the column is
    // cleared by setVisibility, so the route simply can no longer find it.
    $snapshot->setVisibility(SnapshotVisibility::Shared);

    $this->get('/s/'.$token)->assertNotFound();
});

it('REQ-M4-002: each GET /s/{token} writes a snapshot_share_accesses audit row', function (): void {
    RateLimiter::clear('share-link:127.0.0.1');

    $snapshot = linkShareableSnapshot();
    $before = SnapshotShareAccess::query()->count();

    $this->get('/s/'.$snapshot->share_token)->assertOk();

    $access = SnapshotShareAccess::query()->orderByDesc('id')->first();

    expect(SnapshotShareAccess::query()->count())->toBe($before + 1)
        ->and($access->snapshot_id)->toBe($snapshot->id)
        ->and($access->share_token)->toBe($snapshot->share_token);
});

it('REQ-M4-002: /s/{token} is rate-limited per IP', function (): void {
    RateLimiter::clear('share-link:127.0.0.1');

    $snapshot = linkShareableSnapshot();
    $token = $snapshot->share_token;

    // Throttle is 60/min per IP. Hit it 60 times — all should pass; the 61st
    // must return 429.
    for ($i = 0; $i < 60; $i++) {
        $this->get('/s/'.$token)->assertOk();
    }

    $this->get('/s/'.$token)->assertStatus(429);
});
