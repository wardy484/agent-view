<?php

declare(strict_types=1);

use App\Enums\CommentReactionEmoji;
use App\Enums\CommentResolution;
use App\Enums\CommentStatus;
use App\Models\Comment;
use App\Models\CommentReaction;
use App\Models\Snapshot;
use App\Models\SnapshotVersion;
use App\Models\User;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * REQ-M6-017: backend endpoints + frontend hook for optimistic UI on
 * comment writes. Three new HTTP routes (reply create, reaction toggle,
 * status patch) plus a `useOptimisticComments` hook that overlays a
 * mutation queue on top of server-projected comments. Rows carry a
 * client UUID and reconcile when polling returns the canonical row.
 */

/**
 * @return array{snapshot: Snapshot, version: SnapshotVersion, owner: User, workbench: Workbench, root: Comment}
 */
function optimisticEndpointsSetup(): array
{
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snapshot = Snapshot::factory()->for($workbench)->create();

    $blockId = Str::uuid()->toString();

    $version = SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'report',
        dataPayload: ['blocks' => [
            ['id' => $blockId, 'type' => 'markdown', 'body' => 'The quick brown fox jumps over the lazy dog.'],
        ]],
    );

    $root = Comment::factory()->for($snapshot)->create([
        'block_id' => $blockId,
        'created_on_version_id' => $version->id,
        'author_user_id' => $owner->id,
        'body' => 'Initial comment.',
        'anchor_quote' => 'quick brown fox',
        'anchor_prefix' => 'The ',
        'anchor_suffix' => ' jumps',
        'anchor_start_hint' => 4,
        'anchor_end_hint' => 19,
        'status' => CommentStatus::Open->value,
    ]);

    return [
        'snapshot' => $snapshot->fresh(),
        'version' => $version,
        'owner' => $owner,
        'workbench' => $workbench,
        'root' => $root->fresh(),
    ];
}

it('REQ-M6-017: POST /snapshots/{s}/comments/{c}/replies creates a reply for an authorised user', function (): void {
    $base = optimisticEndpointsSetup();

    $response = $this->actingAs($base['owner'])->post(
        '/snapshots/'.$base['snapshot']->id.'/comments/'.$base['root']->id.'/replies',
        ['body' => 'Owner reply text.'],
    );

    $response->assertRedirect();

    $reply = Comment::query()
        ->where('parent_comment_id', $base['root']->id)
        ->first();

    expect($reply)->not->toBeNull();
    expect($reply->body)->toBe('Owner reply text.');
    expect($reply->snapshot_id)->toBe($base['snapshot']->id);
    expect($reply->author_user_id)->toBe($base['owner']->id);
});

it('REQ-M6-017: POST /snapshots/{s}/comments/{c}/replies rejects outsider with 403', function (): void {
    $base = optimisticEndpointsSetup();
    $outsider = User::factory()->create();

    $response = $this->actingAs($outsider)->post(
        '/snapshots/'.$base['snapshot']->id.'/comments/'.$base['root']->id.'/replies',
        ['body' => 'I should not be allowed.'],
    );

    $response->assertForbidden();
    expect(Comment::query()->where('parent_comment_id', $base['root']->id)->count())->toBe(0);
});

it('REQ-M6-017: POST /snapshots/{s}/comments/{c}/reactions toggles a reaction for the authenticated user', function (): void {
    $base = optimisticEndpointsSetup();

    // First call: add.
    $response = $this->actingAs($base['owner'])->post(
        '/snapshots/'.$base['snapshot']->id.'/comments/'.$base['root']->id.'/reactions',
        ['emoji' => CommentReactionEmoji::ThumbsUp->value],
    );
    $response->assertRedirect();

    expect(CommentReaction::query()
        ->where('comment_id', $base['root']->id)
        ->where('user_id', $base['owner']->id)
        ->where('emoji', CommentReactionEmoji::ThumbsUp->value)
        ->count())->toBe(1);

    // Second call: remove.
    $this->actingAs($base['owner'])->post(
        '/snapshots/'.$base['snapshot']->id.'/comments/'.$base['root']->id.'/reactions',
        ['emoji' => CommentReactionEmoji::ThumbsUp->value],
    )->assertRedirect();

    expect(CommentReaction::query()
        ->where('comment_id', $base['root']->id)
        ->where('user_id', $base['owner']->id)
        ->count())->toBe(0);
});

it('REQ-M6-017: POST /snapshots/{s}/comments/{c}/reactions rejects an emoji outside the fixed 6-set', function (): void {
    $base = optimisticEndpointsSetup();

    $response = $this->actingAs($base['owner'])->post(
        '/snapshots/'.$base['snapshot']->id.'/comments/'.$base['root']->id.'/reactions',
        ['emoji' => '🚀'],
    );

    $response->assertSessionHasErrors(['emoji']);
});

it('REQ-M6-017: PATCH /snapshots/{s}/comments/{c}/status flips status to resolved with resolution=user', function (): void {
    $base = optimisticEndpointsSetup();

    $response = $this->actingAs($base['owner'])->patch(
        '/snapshots/'.$base['snapshot']->id.'/comments/'.$base['root']->id.'/status',
        ['status' => 'resolved'],
    );

    $response->assertRedirect();

    $base['root']->refresh();
    expect($base['root']->status)->toBe(CommentStatus::Resolved);
    expect($base['root']->resolution)->toBe(CommentResolution::User);
});

it('REQ-M6-017: PATCH /snapshots/{s}/comments/{c}/status to wontfix is allowed for owner', function (): void {
    $base = optimisticEndpointsSetup();

    $response = $this->actingAs($base['owner'])->patch(
        '/snapshots/'.$base['snapshot']->id.'/comments/'.$base['root']->id.'/status',
        ['status' => 'wontfix'],
    );

    $response->assertRedirect();

    $base['root']->refresh();
    expect($base['root']->status)->toBe(CommentStatus::Wontfix);
});

it('REQ-M6-017: PATCH /snapshots/{s}/comments/{c}/status rejects outsider', function (): void {
    $base = optimisticEndpointsSetup();
    $outsider = User::factory()->create();

    $response = $this->actingAs($outsider)->patch(
        '/snapshots/'.$base['snapshot']->id.'/comments/'.$base['root']->id.'/status',
        ['status' => 'resolved'],
    );

    $response->assertForbidden();
    $base['root']->refresh();
    expect($base['root']->status)->toBe(CommentStatus::Open);
});

it('REQ-M6-017: useOptimisticComments hook ships with rollback and reconcile entry points', function (): void {
    $path = resource_path('js/hooks/use-optimistic-comments.ts');

    expect(file_exists($path))->toBeTrue();

    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('export function useOptimisticComments')
        ->toContain('addOptimisticComment')
        ->toContain('addOptimisticReply')
        ->toContain('toggleOptimisticReaction')
        ->toContain('flipOptimisticStatus')
        ->toContain('rollback')
        ->toContain('reconcile')
        ->toContain('crypto.randomUUID')
        // 10s rollback timeout for unreconciled rows.
        ->toContain('10_000')
        // The hook stores client UUIDs and overlays them on server projections.
        ->toContain('clientId');
});

it('REQ-M6-017: snapshot-sidebar wires optimistic create/reply/reaction/status flows', function (): void {
    $path = resource_path('js/components/nexus/snapshot-sidebar.tsx');

    expect(file_exists($path))->toBeTrue();

    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('useOptimisticComments')
        // REQ-M6-030: the in-sidebar composer is gone, so addOptimisticComment
        // is no longer wired up here — the floating pill (REQ-M6-027) POSTs
        // new root comments directly and the optimistic-create flow now
        // lives in the pill, not the sidebar. Reply / reaction / status
        // helpers still ride on the sidebar's hook.
        // Reply box exists and uses the optimistic helper.
        ->toContain('addOptimisticReply')
        // Reaction toggle helper.
        ->toContain('toggleOptimisticReaction')
        // Status flip helper.
        ->toContain('flipOptimisticStatus')
        // Both new endpoints are POSTed to.
        ->toContain('/replies')
        ->toContain('/reactions')
        ->toContain('/status')
        // Rollback is wired to onError.
        ->toContain('rollback(')
        // Optimistic rows render with a pulse / opacity hint.
        ->toContain('data-optimistic');
});
