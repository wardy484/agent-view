<?php

declare(strict_types=1);

use App\Enums\CommentStatus;
use App\Enums\SnapshotVisibility;
use App\Models\Comment;
use App\Models\Snapshot;
use App\Models\SnapshotShare;
use App\Models\SnapshotVersion;
use App\Models\User;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * REQ-M6-014: snapshot page right sidebar with Comments + History tabs.
 *
 * Backend coverage:
 *  - SnapshotController@show projects `comments` + `versionHistory` Inertia
 *    props (only for authenticated viewers on report views).
 *  - POST /snapshots/{s}/comments stores a root comment for grantees+ and
 *    rejects outsiders.
 *
 * Frontend coverage (file-shape assertions, mirroring REQ-M6-013):
 *  - snapshot-sidebar.tsx ships with the expected tabs + scroll helper.
 *  - report-view.tsx wires the composer into the sidebar.
 */

/**
 * @return array{snapshot: Snapshot, version: SnapshotVersion, owner: User, workbench: Workbench, block_id: string}
 */
function sidebarReportSetup(string $body = 'The quick brown fox jumps over the lazy dog.', ?string $blockId = null): array
{
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snapshot = Snapshot::factory()->for($workbench)->create();
    $blockId ??= Str::uuid()->toString();

    $version = SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'report',
        dataPayload: ['blocks' => [
            ['id' => $blockId, 'type' => 'markdown', 'body' => $body],
        ]],
    );

    return [
        'snapshot' => $snapshot->fresh(['workbench', 'currentVersion']),
        'version' => $version,
        'owner' => $owner,
        'workbench' => $workbench,
        'block_id' => $blockId,
    ];
}

it('REQ-M6-014: SnapshotController@show passes comments prop for authenticated viewers on report views', function (): void {
    $base = sidebarReportSetup();

    Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'body' => 'Looks good but consider tightening this paragraph.',
        'anchor_quote' => 'quick brown fox',
        'anchor_prefix' => 'The ',
        'anchor_suffix' => ' jumps',
        'anchor_start_hint' => strpos('The quick brown fox jumps over the lazy dog.', 'quick brown fox'),
        'anchor_end_hint' => strpos('The quick brown fox jumps over the lazy dog.', 'quick brown fox') + strlen('quick brown fox'),
        'status' => CommentStatus::Open->value,
    ]);

    $response = $this->actingAs($base['owner'])->get(route('workbench.snapshot.show', [
        'workbench' => $base['workbench']->slug,
        'snapshot' => $base['snapshot']->slug,
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('snapshot')
        ->has('comments', 1)
        ->where('comments.0.body', 'Looks good but consider tightening this paragraph.')
        ->where('comments.0.block_id', $base['block_id'])
        ->where('comments.0.status', 'open')
        ->where('comments.0.kind', 'comment')
        ->has('comments.0.anchor.resolved_in_current_version')
        ->has('comments.0.thread')
        ->has('comments.0.reactions_summary'),
    );
});

it('REQ-M6-014: SnapshotController@show passes null comments prop for link-token viewers', function (): void {
    $base = sidebarReportSetup();
    $base['snapshot']->setVisibility(SnapshotVisibility::Link);
    $base['snapshot']->refresh();

    // PublicSnapshotController is the link-token path — it bypasses
    // SnapshotController entirely and never emits a `comments` prop.
    $response = $this->get('/s/'.$base['snapshot']->share_token);

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('snapshot')
        ->where('is_public_link', true)
        ->missing('comments')
        ->missing('versionHistory'),
    );
});

it('REQ-M6-014: SnapshotController@show does not pass comments prop on non-report views', function (): void {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snapshot = Snapshot::factory()->for($workbench)->create();

    SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: [
            'columns' => [['key' => 'id', 'label' => 'ID']],
            'rows' => [['id' => 1]],
        ],
    );

    $response = $this->actingAs($owner)->get(route('workbench.snapshot.show', [
        'workbench' => $workbench->slug,
        'snapshot' => $snapshot->slug,
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('snapshot')
        ->where('comments', null)
        ->where('versionHistory', null),
    );
});

it('REQ-M6-014: SnapshotController@show passes versionHistory prop with revisions in newest-first order', function (): void {
    $base = sidebarReportSetup();

    SnapshotVersioning::append(
        snapshot: $base['snapshot'],
        viewType: 'report',
        dataPayload: ['blocks' => [
            ['id' => $base['block_id'], 'type' => 'markdown', 'body' => 'Edited body for v2.'],
        ]],
        metadata: ['summary' => 'Tightened the intro.', 'author_kind' => 'user'],
    );

    $response = $this->actingAs($base['owner'])->get(route('workbench.snapshot.show', [
        'workbench' => $base['workbench']->slug,
        'snapshot' => $base['snapshot']->slug,
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('snapshot')
        ->has('versionHistory', 2)
        // Newest first: v2 then v1.
        ->where('versionHistory.0.revision', 2)
        ->where('versionHistory.0.summary', 'Tightened the intro.')
        ->where('versionHistory.0.author_kind', 'user')
        ->where('versionHistory.1.revision', 1),
    );
});

it('REQ-M6-014: POST /snapshots/{s}/comments creates a root comment for an active grantee', function (): void {
    $base = sidebarReportSetup();

    $grantee = User::factory()->create();
    SnapshotShare::factory()
        ->for($base['snapshot'])
        ->for($grantee, 'user')
        ->create([
            'granted_by_user_id' => $base['owner']->id,
            'email' => $grantee->email,
        ]);

    $response = $this->actingAs($grantee)->post('/snapshots/'.$base['snapshot']->id.'/comments', [
        'block_id' => $base['block_id'],
        'kind' => 'comment',
        'body' => 'Nice section!',
        'anchor_quote' => 'quick brown fox',
        'anchor_prefix' => 'The ',
        'anchor_suffix' => ' jumps',
        'anchor_start_hint' => 4,
        'anchor_end_hint' => 19,
    ]);

    $response->assertRedirect();

    $created = Comment::query()
        ->where('snapshot_id', $base['snapshot']->id)
        ->where('author_user_id', $grantee->id)
        ->first();

    expect($created)->not->toBeNull();
    expect($created->body)->toBe('Nice section!');
    expect($created->kind->value)->toBe('comment');
    expect($created->parent_comment_id)->toBeNull();
    expect((int) $created->created_on_version_id)->toBe((int) $base['version']->id);
});

it('REQ-M6-014: POST /snapshots/{s}/comments rejects an outsider with 403', function (): void {
    $base = sidebarReportSetup();
    $outsider = User::factory()->create();

    $response = $this->actingAs($outsider)->post('/snapshots/'.$base['snapshot']->id.'/comments', [
        'block_id' => $base['block_id'],
        'kind' => 'comment',
        'body' => 'I should not be allowed to do this.',
        'anchor_quote' => 'quick brown fox',
        'anchor_prefix' => 'The ',
        'anchor_suffix' => ' jumps',
        'anchor_start_hint' => 4,
        'anchor_end_hint' => 19,
    ]);

    $response->assertForbidden();
    expect(Comment::query()->where('snapshot_id', $base['snapshot']->id)->count())->toBe(0);
});

it('REQ-M6-014: POST /snapshots/{s}/comments validates required anchor fields when not a reply', function (): void {
    $base = sidebarReportSetup();

    $response = $this->actingAs($base['owner'])->post('/snapshots/'.$base['snapshot']->id.'/comments', [
        // missing block_id, anchor_quote, anchor_*_hint
        'kind' => 'comment',
        'body' => 'No anchor data.',
    ]);

    $response->assertSessionHasErrors([
        'block_id',
        'anchor_quote',
        'anchor_start_hint',
        'anchor_end_hint',
    ]);
});

it('REQ-M6-014: snapshot-sidebar component exists with Comments and History tabs', function (): void {
    $path = resource_path('js/components/nexus/snapshot-sidebar.tsx');

    expect(file_exists($path))->toBeTrue();

    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('export function SnapshotSidebar')
        ->toContain('export function scrollToAndHighlightAnchor')
        // Two tabs in the expected order, defaulting to Comments.
        ->toContain('label="Comments"')
        ->toContain('label="History"')
        ->toContain("useState<Tab>('comments')")
        // Comments tab groups by block_id in document order.
        ->toContain('groupCommentsByBlock')
        ->toContain('blockOrder')
        // Status badge supports the four lifecycle states.
        ->toContain("'open'")
        ->toContain("'resolved'")
        ->toContain("'stale'")
        ->toContain("'wontfix'")
        // Reactions and thread are rendered inline.
        ->toContain('reactions_summary')
        ->toContain('comment.thread.map')
        // Composer POSTs to the create endpoint.
        ->toContain('snapshots/${snapshotId}/comments')
        ->toContain('preserveScroll: true')
        // Stale toast wired up.
        ->toContain('Anchor not in current revision (stale)');
});

it('REQ-M6-014: report-view mounts the sidebar and wires onComment/onSuggest to a composer', function (): void {
    $path = resource_path('js/components/nexus/report-view.tsx');

    expect(file_exists($path))->toBeTrue();

    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('SnapshotSidebar')
        ->toContain('composerSelection')
        ->toContain("openComposer('comment')")
        ->toContain("openComposer('suggestion')")
        // Two-column layout collapses on narrow screens via Tailwind lg:.
        ->toContain('lg:flex-row')
        ->toContain('blockOrder');
});

it('REQ-M6-014: scrollToAndHighlightAnchor helper handles stale anchors gracefully', function (): void {
    $path = resource_path('js/components/nexus/snapshot-sidebar.tsx');
    $source = (string) file_get_contents($path);

    expect($source)
        // Locates the markdown block by its data attribute.
        ->toContain('data-comment-block-id="${blockId}"')
        // Walks descendant text nodes via TreeWalker.
        ->toContain('createTreeWalker')
        ->toContain('NodeFilter.SHOW_TEXT')
        // Falls back to scrolling the block when the quote can't be located.
        ->toContain('scrollIntoView')
        // Wraps the matched range in a temporary <mark> with a fade-out.
        ->toContain('comment-highlight')
        ->toContain('surroundContents')
        // Returns false on stale resolution so the caller can toast.
        ->toContain('return false');
});
