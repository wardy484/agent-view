<?php

declare(strict_types=1);

use App\Enums\CommentKind;
use App\Enums\CommentReactionEmoji;
use App\Enums\CommentStatus;
use App\Mcp\Servers\NexusServer;
use App\Mcp\Tools\ResolveComments;
use App\Models\Comment;
use App\Models\CommentReaction;
use App\Models\Snapshot;
use App\Models\SnapshotVersion;
use App\Models\User;
use App\Models\Workbench;
use App\Nexus\Comments\CommentReactionService;
use App\Nexus\Comments\SuggestionAcceptanceService;
use App\Nexus\SnapshotVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * REQ-M6-016: comments_revision counter + polling delta endpoint.
 *
 * Backend coverage:
 *  - Counter starts at 0 and bumps on every comment / reply / reaction /
 *    resolution write.
 *  - GET /snapshots/{id}/sidebar projects the same shape as the page show
 *    endpoint, with a `no_change` short-circuit when ?since= matches.
 *
 * Frontend coverage (file-shape assertions, mirroring REQ-M6-014):
 *  - useSidebarPolling hook gates on document.visibilityState.
 *  - The hook is mounted on the snapshot page.
 */

/**
 * @return array{snapshot: Snapshot, version: SnapshotVersion, owner: User, workbench: Workbench, block_id: string}
 */
function pollingReportSetup(): array
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

    return [
        'snapshot' => $snapshot->fresh(['workbench', 'currentVersion']),
        'version' => $version,
        'owner' => $owner,
        'workbench' => $workbench,
        'block_id' => $blockId,
    ];
}

it('REQ-M6-016: snapshots.comments_revision starts at 0', function (): void {
    expect(Schema::hasColumn('snapshots', 'comments_revision'))->toBeTrue();

    $base = pollingReportSetup();

    expect((int) $base['snapshot']->fresh()->comments_revision)->toBe(0);
});

it('REQ-M6-016: creating a comment bumps comments_revision', function (): void {
    $base = pollingReportSetup();

    expect((int) $base['snapshot']->fresh()->comments_revision)->toBe(0);

    Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'body' => 'Initial.',
        'anchor_quote' => 'quick brown fox',
        'anchor_prefix' => 'The ',
        'anchor_suffix' => ' jumps',
        'anchor_start_hint' => 4,
        'anchor_end_hint' => 19,
        'status' => CommentStatus::Open->value,
    ]);

    expect((int) $base['snapshot']->fresh()->comments_revision)->toBe(1);
});

it('REQ-M6-016: creating a reaction bumps comments_revision', function (): void {
    $base = pollingReportSetup();
    $comment = Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'anchor_quote' => 'quick brown fox',
        'anchor_prefix' => 'The ',
        'anchor_suffix' => ' jumps',
        'anchor_start_hint' => 4,
        'anchor_end_hint' => 19,
    ]);

    $beforeReaction = (int) $base['snapshot']->fresh()->comments_revision;

    (new CommentReactionService)->toggle($comment, $base['owner'], CommentReactionEmoji::ThumbsUp);

    expect((int) $base['snapshot']->fresh()->comments_revision)->toBe($beforeReaction + 1);
});

it('REQ-M6-016: deleting a reaction bumps comments_revision', function (): void {
    $base = pollingReportSetup();
    $comment = Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'anchor_quote' => 'quick brown fox',
        'anchor_prefix' => 'The ',
        'anchor_suffix' => ' jumps',
        'anchor_start_hint' => 4,
        'anchor_end_hint' => 19,
    ]);

    $service = new CommentReactionService;
    $service->toggle($comment, $base['owner'], CommentReactionEmoji::ThumbsUp);

    $afterAdd = (int) $base['snapshot']->fresh()->comments_revision;

    // Toggle a second time → reaction is removed.
    $service->toggle($comment, $base['owner'], CommentReactionEmoji::ThumbsUp);

    expect(CommentReaction::query()->where('comment_id', $comment->id)->count())->toBe(0);
    expect((int) $base['snapshot']->fresh()->comments_revision)->toBe($afterAdd + 1);
});

it('REQ-M6-016: resolving a comment via MCP bumps comments_revision', function (): void {
    $base = pollingReportSetup();
    $comment = Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'anchor_quote' => 'quick brown fox',
        'anchor_prefix' => 'The ',
        'anchor_suffix' => ' jumps',
        'anchor_start_hint' => 4,
        'anchor_end_hint' => 19,
        'status' => CommentStatus::Open->value,
    ]);

    $before = (int) $base['snapshot']->fresh()->comments_revision;

    Sanctum::actingAs($base['owner']);

    NexusServer::tool(ResolveComments::class, [
        'comment_ids' => [$comment->id],
    ])->assertOk();

    expect($comment->fresh()->status)->toBe(CommentStatus::Resolved);
    expect((int) $base['snapshot']->fresh()->comments_revision)->toBeGreaterThan($before);
});

it('REQ-M6-016: SuggestionAcceptanceService bumps comments_revision', function (): void {
    $base = pollingReportSetup();

    $suggestion = Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'kind' => CommentKind::Suggestion->value,
        'body' => 'Maybe rephrase.',
        'proposed_text' => 'speedy chestnut fox',
        'anchor_quote' => 'quick brown fox',
        'anchor_prefix' => 'The ',
        'anchor_suffix' => ' jumps',
        'anchor_start_hint' => 4,
        'anchor_end_hint' => 19,
        'status' => CommentStatus::Open->value,
    ]);

    $before = (int) $base['snapshot']->fresh()->comments_revision;

    (new SuggestionAcceptanceService)->accept($suggestion, $base['owner']);

    // Suggestion acceptance flips status + resolution + addressed_on_version_id
    // — every column we watch for in Comment::booted's `updated` hook, so the
    // counter must move forward by at least one.
    expect((int) $base['snapshot']->fresh()->comments_revision)->toBeGreaterThan($before);
});

it('REQ-M6-016: GET /snapshots/{id}/sidebar returns the projected payload', function (): void {
    $base = pollingReportSetup();

    Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'body' => 'Looks good.',
        'anchor_quote' => 'quick brown fox',
        'anchor_prefix' => 'The ',
        'anchor_suffix' => ' jumps',
        'anchor_start_hint' => 4,
        'anchor_end_hint' => 19,
    ]);

    $response = $this->actingAs($base['owner'])
        ->getJson('/snapshots/'.$base['snapshot']->id.'/sidebar');

    $response->assertOk();
    $response->assertJsonStructure([
        'comments_revision',
        'current_revision',
        'current_version_id',
        'no_change',
        'comments',
        'versionHistory',
    ]);
    $response->assertJsonPath('no_change', false);
    $response->assertJsonPath('current_revision', (int) $base['version']->revision);
    $response->assertJsonPath('comments.0.body', 'Looks good.');
});

it('REQ-M6-016: GET /snapshots/{id}/sidebar?since=N returns no_change when revision unchanged', function (): void {
    $base = pollingReportSetup();

    Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'body' => 'First comment.',
        'anchor_quote' => 'quick brown fox',
        'anchor_prefix' => 'The ',
        'anchor_suffix' => ' jumps',
        'anchor_start_hint' => 4,
        'anchor_end_hint' => 19,
    ]);

    $current = (int) $base['snapshot']->fresh()->comments_revision;

    $response = $this->actingAs($base['owner'])
        ->getJson('/snapshots/'.$base['snapshot']->id.'/sidebar?since='.$current);

    $response->assertOk();
    $response->assertJsonPath('no_change', true);
    $response->assertJsonPath('comments_revision', $current);
    $response->assertJsonMissing(['comments' => null], false);
});

it('REQ-M6-016: GET /snapshots/{id}/sidebar requires view permission', function (): void {
    $base = pollingReportSetup();
    $outsider = User::factory()->create();

    $response = $this->actingAs($outsider)
        ->getJson('/snapshots/'.$base['snapshot']->id.'/sidebar');

    $response->assertForbidden();

    // Unauthenticated → the web auth middleware kicks in before the
    // controller. Either a redirect (default) or a 401/403 status is
    // acceptable; what we assert is that the payload never leaks.
    $unauth = $this->getJson('/snapshots/'.$base['snapshot']->id.'/sidebar');
    expect($unauth->status())->toBeIn([302, 401, 403]);
    expect($unauth->json('comments'))->toBeNull();
});

it('REQ-M6-016: useSidebarPolling hook gates on document.visibilityState (source assertion)', function (): void {
    $path = resource_path('js/hooks/use-sidebar-polling.ts');

    expect(file_exists($path))->toBeTrue();

    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('export function useSidebarPolling')
        ->toContain("document.visibilityState !== 'visible'")
        ->toContain('setInterval')
        ->toContain('8000')
        ->toContain('visibilitychange')
        ->toContain('clearInterval')
        ->toContain('removeEventListener')
        ->toContain('isHistoricalView')
        ->toContain('router.reload')
        ->toContain("'comments'")
        ->toContain("'versionHistory'");
});

it('REQ-M6-016: useSidebarPolling hook is wired on the snapshot page (source assertion)', function (): void {
    $path = resource_path('js/pages/snapshot.tsx');

    expect(file_exists($path))->toBeTrue();

    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain("import { useSidebarPolling } from '@/hooks/use-sidebar-polling'")
        ->toContain('useSidebarPolling(')
        ->toContain('comments_revision');
});
