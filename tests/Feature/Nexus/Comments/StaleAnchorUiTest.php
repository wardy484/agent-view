<?php

declare(strict_types=1);

use App\Enums\CommentStatus;
use App\Models\Comment;
use App\Models\Snapshot;
use App\Models\User;
use App\Models\Workbench;
use App\Nexus\Comments\CommentProjection;
use App\Nexus\SnapshotVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * REQ-M6-019: stale comments render in the sidebar with a muted style, an
 * "Anchor lost" chip, and the original anchor_quote shown verbatim. Reply
 * and React stay enabled; Resolve and Accept are disabled with an
 * explanatory tooltip. Auto-revival (per REQ-M6-012) is implicit — when
 * polling brings down a fresh anchor that resolves, the card simply
 * re-renders without the muted styling.
 *
 * The UI work is frontend-only, so these are source-assertion tests
 * mirroring the REQ-M6-014/M6-018 style (see SnapshotSidebarTest.php).
 */
it('REQ-M6-019: snapshot-sidebar branches on isStale combining status and resolved_in_current_version', function (): void {
    $path = resource_path('js/components/nexus/snapshot-sidebar.tsx');

    expect(file_exists($path))->toBeTrue();

    $source = (string) file_get_contents($path);

    expect($source)
        // The combined predicate per the REQ: either lifecycle status is
        // stale, OR the projected anchor doesn't resolve in this revision.
        ->toContain('isStale')
        ->toContain("comment.status === 'stale'")
        ->toContain('!comment.anchor.resolved_in_current_version');
});

it('REQ-M6-019: stale comment card uses muted styling (opacity / grayscale)', function (): void {
    $path = resource_path('js/components/nexus/snapshot-sidebar.tsx');
    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('opacity-60')
        ->toContain('grayscale')
        ->toContain('data-stale');
});

it('REQ-M6-019: stale comment renders an Anchor lost chip', function (): void {
    $path = resource_path('js/components/nexus/snapshot-sidebar.tsx');
    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('Anchor lost')
        ->toContain('snapshot-sidebar-comment-anchor-lost-chip');
});

it('REQ-M6-019: stale comment keeps Reply and React enabled', function (): void {
    $path = resource_path('js/components/nexus/snapshot-sidebar.tsx');
    $source = (string) file_get_contents($path);

    // Reply and React remain enabled when only stale (not optimistic /
    // historical). The component must compute their disabled state from
    // `actionsDisabled` only, NOT from `isStale`.
    expect($source)
        ->toContain('snapshot-sidebar-comment-reply-toggle')
        ->toContain('snapshot-sidebar-comment-react-toggle')
        // Both buttons keep their existing `disabled={actionsDisabled}` —
        // the stale branch must not flip them off.
        ->toMatch('/disabled=\{actionsDisabled\}\s*\n\s*data-testid="snapshot-sidebar-comment-reply-toggle"/')
        ->toMatch('/disabled=\{actionsDisabled\}\s*\n\s*data-testid="snapshot-sidebar-comment-react-toggle"/');
});

it('REQ-M6-019: stale comment disables Resolve and Accept controls with a tooltip', function (): void {
    $path = resource_path('js/components/nexus/snapshot-sidebar.tsx');
    $source = (string) file_get_contents($path);

    expect($source)
        // Tooltip / accessible explanation when stale.
        ->toContain('Anchor not found in current revision')
        // Status select must be disabled when isStale (Resolve action).
        ->toContain('snapshot-sidebar-comment-status-select')
        ->toContain('actionsDisabled || isStale')
        // Accept-suggestion button exists for suggestion comments and
        // routes to the existing accept endpoint (REQ-M6-008).
        ->toContain('snapshot-sidebar-comment-accept')
        ->toContain('comments/${comment.id}/accept');
});

it('REQ-M6-019: stale comment shows the original anchor.quote verbatim', function (): void {
    $path = resource_path('js/components/nexus/snapshot-sidebar.tsx');
    $source = (string) file_get_contents($path);

    // The full anchor.quote (NOT the truncated form) must be available
    // verbatim when the anchor is stale, so reviewers can still see what
    // the comment was anchored to even though the text is gone from the
    // current revision.
    expect($source)
        ->toContain('comment.anchor.quote')
        ->toContain('snapshot-sidebar-comment-stale-quote');
});

it('REQ-M6-019: CommentProjection sets resolved_in_current_version=false when the quote disappears', function (): void {
    // Backend sanity check — if the projection ever stops flagging
    // unresolved anchors, the frontend stale UI breaks silently. We re-
    // exercise the basic shape here; the deeper anchor-resolution
    // semantics are tested in AnchorResolverTest.
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snapshot = Snapshot::factory()->for($workbench)->create();
    $blockId = Str::uuid()->toString();

    $original = SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'report',
        dataPayload: ['blocks' => [
            ['id' => $blockId, 'type' => 'markdown', 'body' => 'The quick brown fox jumps over the lazy dog.'],
        ]],
    );

    $comment = Comment::factory()->for($snapshot)->create([
        'block_id' => $blockId,
        'created_on_version_id' => $original->id,
        'author_user_id' => $owner->id,
        'body' => 'Tighten this.',
        'anchor_quote' => 'quick brown fox',
        'anchor_prefix' => 'The ',
        'anchor_suffix' => ' jumps',
        'anchor_start_hint' => 4,
        'anchor_end_hint' => 19,
        'status' => CommentStatus::Open->value,
    ]);

    // New revision rewrites the block — the anchored quote is gone.
    $newVersion = SnapshotVersioning::append(
        snapshot: $snapshot->fresh(),
        viewType: 'report',
        dataPayload: ['blocks' => [
            ['id' => $blockId, 'type' => 'markdown', 'body' => 'Entirely different prose now.'],
        ]],
    );

    $payload = CommentProjection::project($comment->fresh(['author', 'createdOnVersion', 'addressedOnVersion', 'replies.author', 'reactions']), $newVersion);

    expect($payload['anchor']['resolved_in_current_version'])->toBeFalse();
    expect($payload['anchor']['quote'])->toBe('quick brown fox');
});
