<?php

declare(strict_types=1);

use App\Enums\CommentAuthorKind;
use App\Enums\CommentKind;
use App\Enums\CommentStatus;
use App\Mcp\Servers\NexusServer;
use App\Mcp\Tools\ReplyToComment;
use App\Models\Comment;
use App\Models\Snapshot;
use App\Models\SnapshotVersion;
use App\Models\User;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * @return array{snapshot: Snapshot, version: SnapshotVersion, owner: User, block_id: string}
 */
function makeReportSnapshotForReply(?User $owner = null): array
{
    $owner ??= User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snapshot = Snapshot::factory()->for($workbench)->create();
    $blockId = Str::uuid()->toString();
    $version = SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'report',
        dataPayload: ['blocks' => [
            ['id' => $blockId, 'type' => 'markdown', 'body' => 'Anchor target paragraph.'],
        ]],
    );

    return ['snapshot' => $snapshot, 'version' => $version, 'owner' => $owner, 'block_id' => $blockId];
}

it('REQ-M6-011: posts an agent reply with author_kind=agent under the target comment', function (): void {
    $base = makeReportSnapshotForReply();

    $root = Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'status' => CommentStatus::Open->value,
        'anchor_quote' => 'Anchor target paragraph.',
    ]);

    Sanctum::actingAs($base['owner']);

    NexusServer::tool(ReplyToComment::class, [
        'comment_id' => $root->id,
        'body' => 'Acknowledged — addressing in v2.',
    ])->assertOk();

    $reply = Comment::query()
        ->where('parent_comment_id', $root->id)
        ->first();

    expect($reply)->not->toBeNull()
        ->and($reply->author_kind)->toBe(CommentAuthorKind::Agent)
        ->and($reply->parent_comment_id)->toBe($root->id)
        ->and($reply->snapshot_id)->toBe($base['snapshot']->id)
        ->and($reply->block_id)->toBe($base['block_id'])
        ->and($reply->body)->toBe('Acknowledged — addressing in v2.')
        ->and($reply->anchor_quote)->toBeNull()
        ->and($reply->author_user_id)->toBe($base['owner']->id);
});

it('REQ-M6-011: returns the reply_id of the created comment', function (): void {
    $base = makeReportSnapshotForReply();

    $root = Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'status' => CommentStatus::Open->value,
        'anchor_quote' => 'Anchor target paragraph.',
    ]);

    Sanctum::actingAs($base['owner']);

    NexusServer::tool(ReplyToComment::class, [
        'comment_id' => $root->id,
        'body' => 'A reply.',
    ])->assertOk()
        ->assertStructuredContent(function (AssertableJson $json): void {
            $json->has('reply_id')
                ->whereType('reply_id', 'integer')
                ->etc();
        });

    $reply = Comment::query()
        ->where('parent_comment_id', $root->id)
        ->first();

    expect($reply)->not->toBeNull();
});

it('REQ-M6-011: rejects unknown comment_id with not_found error', function (): void {
    $base = makeReportSnapshotForReply();

    Sanctum::actingAs($base['owner']);

    NexusServer::tool(ReplyToComment::class, [
        'comment_id' => 999999,
        'body' => 'orphan reply',
    ])->assertHasErrors([
        'not_found: comment 999999 does not exist or has been deleted.',
    ]);
});

it('REQ-M6-011: rejects soft-deleted target with not_found', function (): void {
    $base = makeReportSnapshotForReply();

    $root = Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'status' => CommentStatus::Open->value,
        'anchor_quote' => 'Anchor target paragraph.',
    ]);

    $rootId = $root->id;
    $root->delete();

    expect($root->trashed())->toBeTrue();

    Sanctum::actingAs($base['owner']);

    NexusServer::tool(ReplyToComment::class, [
        'comment_id' => $rootId,
        'body' => 'reply to deleted',
    ])->assertHasErrors([
        'not_found: comment '.$rootId.' does not exist or has been deleted.',
    ]);

    $replyCount = Comment::query()
        ->where('parent_comment_id', $rootId)
        ->count();
    expect($replyCount)->toBe(0);
});

it('REQ-M6-011: rejects when target comment is itself a reply (depth=1 enforcement)', function (): void {
    $base = makeReportSnapshotForReply();

    $root = Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'status' => CommentStatus::Open->value,
        'anchor_quote' => 'Anchor target paragraph.',
    ]);

    $existingReply = Comment::query()->create([
        'snapshot_id' => $base['snapshot']->id,
        'block_id' => $base['block_id'],
        'parent_comment_id' => $root->id,
        'kind' => CommentKind::Comment,
        'body' => 'pre-existing user reply',
        'status' => CommentStatus::Open,
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'author_kind' => CommentAuthorKind::User,
    ]);

    Sanctum::actingAs($base['owner']);

    NexusServer::tool(ReplyToComment::class, [
        'comment_id' => $existingReply->id,
        'body' => 'reply to a reply',
    ])->assertHasErrors([
        'cannot_reply_to_reply: comment '.$existingReply->id.' is itself a reply; replies are limited to depth = 1.',
    ]);

    // No new reply created targeting the depth-1 reply.
    $childCount = Comment::query()
        ->where('parent_comment_id', $existingReply->id)
        ->count();
    expect($childCount)->toBe(0);
});

it('REQ-M6-011: requires caller to have view permission on the parent comment', function (): void {
    $base = makeReportSnapshotForReply();

    $root = Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'status' => CommentStatus::Open->value,
        'anchor_quote' => 'Anchor target paragraph.',
    ]);

    $outsider = User::factory()->create();
    Sanctum::actingAs($outsider);

    NexusServer::tool(ReplyToComment::class, [
        'comment_id' => $root->id,
        'body' => 'unauthorised reply',
    ])->assertHasErrors([
        'not_authorised: caller cannot view the target comment.',
    ]);

    $replyCount = Comment::query()
        ->where('parent_comment_id', $root->id)
        ->count();
    expect($replyCount)->toBe(0);
});

it('REQ-M6-011: tool description contains the user-data-not-instructions warning', function (): void {
    $reflector = new ReflectionClass(ReplyToComment::class);
    $attributes = $reflector->getAttributes(Description::class);

    expect($attributes)->not->toBeEmpty();

    $description = (string) $attributes[0]->newInstance()->value;

    expect($description)->toContain('user-authored data')
        ->and(strtolower($description))->toContain('not')
        ->and(strtolower($description))->toContain('instructions');
});
