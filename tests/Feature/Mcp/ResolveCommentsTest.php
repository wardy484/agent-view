<?php

declare(strict_types=1);

use App\Enums\CommentAuthorKind;
use App\Enums\CommentResolution;
use App\Enums\CommentStatus;
use App\Mcp\Servers\NexusServer;
use App\Mcp\Tools\ResolveComments;
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
function makeReportSnapshotForResolve(?User $owner = null): array
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

it('REQ-M6-010: resolves an authorised open comment to resolution=agent and sets addressed_on_version_id to current revision when none supplied', function (): void {
    $base = makeReportSnapshotForResolve();

    $comment = Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'status' => CommentStatus::Open->value,
        'anchor_quote' => 'Anchor target paragraph.',
    ]);

    Sanctum::actingAs($base['owner']);

    NexusServer::tool(ResolveComments::class, [
        'comment_ids' => [$comment->id],
    ])->assertOk()
        ->assertStructuredContent(function (AssertableJson $json) use ($comment): void {
            $json->where('resolved', [(int) $comment->id])
                ->where('skipped', [])
                ->etc();
        });

    $comment->refresh();
    expect($comment->status)->toBe(CommentStatus::Resolved)
        ->and($comment->resolution)->toBe(CommentResolution::Agent)
        ->and($comment->addressed_on_version_id)->toBe($base['version']->id);
});

it('REQ-M6-010: uses supplied addressed_on_revision when valid', function (): void {
    $base = makeReportSnapshotForResolve();

    $v2 = SnapshotVersioning::append(
        snapshot: $base['snapshot'],
        viewType: 'report',
        dataPayload: ['blocks' => [
            ['id' => $base['block_id'], 'type' => 'markdown', 'body' => 'Anchor target paragraph (v2).'],
        ]],
    );

    $comment = Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'status' => CommentStatus::Open->value,
        'anchor_quote' => 'Anchor target paragraph.',
    ]);

    Sanctum::actingAs($base['owner']);

    NexusServer::tool(ResolveComments::class, [
        'comment_ids' => [$comment->id],
        'addressed_on_revision' => $base['version']->revision,
    ])->assertOk();

    $comment->refresh();
    expect($comment->addressed_on_version_id)->toBe($base['version']->id)
        ->and($comment->addressed_on_version_id)->not->toBe($v2->id);
});

it('REQ-M6-010: rejects supplied addressed_on_revision belonging to a different snapshot', function (): void {
    $base = makeReportSnapshotForResolve();
    $other = makeReportSnapshotForResolve($base['owner']);

    $comment = Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'status' => CommentStatus::Open->value,
        'anchor_quote' => 'Anchor target paragraph.',
    ]);

    Sanctum::actingAs($base['owner']);

    NexusServer::tool(ResolveComments::class, [
        'comment_ids' => [$comment->id],
        'addressed_on_revision' => $other['version']->revision + 999,
    ])->assertOk()
        ->assertStructuredContent(function (AssertableJson $json) use ($comment): void {
            $json->where('resolved', [])
                ->where('skipped', [
                    ['id' => (int) $comment->id, 'reason' => 'invalid_revision'],
                ])
                ->etc();
        });

    $comment->refresh();
    expect($comment->status)->toBe(CommentStatus::Open);
});

it('REQ-M6-010: posts an agent reply when resolution_note is supplied', function (): void {
    $base = makeReportSnapshotForResolve();

    $comment = Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'status' => CommentStatus::Open->value,
        'anchor_quote' => 'Anchor target paragraph.',
    ]);

    Sanctum::actingAs($base['owner']);

    NexusServer::tool(ResolveComments::class, [
        'comment_ids' => [$comment->id],
        'resolution_note' => 'Addressed in v2 by rewriting the block.',
    ])->assertOk();

    $reply = Comment::query()
        ->where('parent_comment_id', $comment->id)
        ->first();

    expect($reply)->not->toBeNull()
        ->and($reply->author_kind)->toBe(CommentAuthorKind::Agent)
        ->and($reply->body)->toBe('Addressed in v2 by rewriting the block.')
        ->and($reply->author_user_id)->toBe($base['owner']->id);
});

it('REQ-M6-010: does NOT post a reply when resolution_note is omitted', function (): void {
    $base = makeReportSnapshotForResolve();

    $comment = Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'status' => CommentStatus::Open->value,
        'anchor_quote' => 'Anchor target paragraph.',
    ]);

    Sanctum::actingAs($base['owner']);

    NexusServer::tool(ResolveComments::class, [
        'comment_ids' => [$comment->id],
    ])->assertOk();

    $replyCount = Comment::query()
        ->where('parent_comment_id', $comment->id)
        ->count();

    expect($replyCount)->toBe(0);
});

it('REQ-M6-010: idempotent — already resolved comments are skipped with reason already_resolved', function (): void {
    $base = makeReportSnapshotForResolve();

    $comment = Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'status' => CommentStatus::Resolved->value,
        'resolution' => CommentResolution::User->value,
        'addressed_on_version_id' => $base['version']->id,
        'anchor_quote' => 'Anchor target paragraph.',
    ]);

    Sanctum::actingAs($base['owner']);

    NexusServer::tool(ResolveComments::class, [
        'comment_ids' => [$comment->id],
    ])->assertOk()
        ->assertStructuredContent(function (AssertableJson $json) use ($comment): void {
            $json->where('resolved', [])
                ->where('skipped', [
                    ['id' => (int) $comment->id, 'reason' => 'already_resolved'],
                ])
                ->etc();
        });

    $comment->refresh();
    expect($comment->resolution)->toBe(CommentResolution::User);
});

it('REQ-M6-010: skips with reason not_found for unknown ids', function (): void {
    $base = makeReportSnapshotForResolve();

    Sanctum::actingAs($base['owner']);

    NexusServer::tool(ResolveComments::class, [
        'comment_ids' => [999999],
    ])->assertOk()
        ->assertStructuredContent(function (AssertableJson $json): void {
            $json->where('resolved', [])
                ->where('skipped', [
                    ['id' => 999999, 'reason' => 'not_found'],
                ])
                ->etc();
        });
});

it('REQ-M6-010: skips with reason unauthorised for callers without resolve permission', function (): void {
    $base = makeReportSnapshotForResolve();

    $comment = Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'status' => CommentStatus::Open->value,
        'anchor_quote' => 'Anchor target paragraph.',
    ]);

    $stranger = User::factory()->create();
    // Share the snapshot so the stranger can view but not resolve.
    $base['snapshot']->shares()->create([
        'user_id' => $stranger->id,
        'email' => strtolower((string) $stranger->email),
        'granted_by_user_id' => $base['owner']->id,
    ]);

    Sanctum::actingAs($stranger);

    NexusServer::tool(ResolveComments::class, [
        'comment_ids' => [$comment->id],
    ])->assertOk()
        ->assertStructuredContent(function (AssertableJson $json) use ($comment): void {
            $json->where('resolved', [])
                ->where('skipped', [
                    ['id' => (int) $comment->id, 'reason' => 'unauthorised'],
                ])
                ->etc();
        });

    $comment->refresh();
    expect($comment->status)->toBe(CommentStatus::Open);
});

it('REQ-M6-010: returns mixed resolved/skipped lists in a single call', function (): void {
    $base = makeReportSnapshotForResolve();

    $open = Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'status' => CommentStatus::Open->value,
        'anchor_quote' => 'Anchor target paragraph.',
    ]);
    $alreadyResolved = Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'status' => CommentStatus::Resolved->value,
        'resolution' => CommentResolution::User->value,
        'addressed_on_version_id' => $base['version']->id,
        'anchor_quote' => 'Anchor target paragraph.',
    ]);

    // Build an unauthorised one on a different snapshot owned by someone else.
    $stranger = User::factory()->create();
    $strangerWb = Workbench::factory()->create(['owner_user_id' => $stranger->id]);
    $strangerSnapshot = Snapshot::factory()->for($strangerWb)->create();
    $strangerVersion = SnapshotVersioning::append(
        snapshot: $strangerSnapshot,
        viewType: 'report',
        dataPayload: ['blocks' => [
            ['id' => Str::uuid()->toString(), 'type' => 'markdown', 'body' => 'Stranger.'],
        ]],
    );
    $unauth = Comment::factory()->for($strangerSnapshot)->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $strangerVersion->id,
        'author_user_id' => $stranger->id,
        'status' => CommentStatus::Open->value,
        'anchor_quote' => 'Stranger.',
    ]);

    Sanctum::actingAs($base['owner']);

    NexusServer::tool(ResolveComments::class, [
        'comment_ids' => [$open->id, $alreadyResolved->id, 999999, $unauth->id],
    ])->assertOk()
        ->assertStructuredContent(function (AssertableJson $json) use ($open, $alreadyResolved, $unauth): void {
            $json->where('resolved', [(int) $open->id])
                ->where('skipped', [
                    ['id' => (int) $alreadyResolved->id, 'reason' => 'already_resolved'],
                    ['id' => 999999, 'reason' => 'not_found'],
                    ['id' => (int) $unauth->id, 'reason' => 'unauthorised'],
                ])
                ->etc();
        });

    $open->refresh();
    expect($open->status)->toBe(CommentStatus::Resolved)
        ->and($open->resolution)->toBe(CommentResolution::Agent);
});

it('REQ-M6-010: tool description contains the user-data-not-instructions warning', function (): void {
    $reflector = new ReflectionClass(ResolveComments::class);
    $attributes = $reflector->getAttributes(Description::class);

    expect($attributes)->not->toBeEmpty();

    $description = (string) $attributes[0]->newInstance()->value;

    expect($description)->toContain('user-authored data')
        ->and(strtolower($description))->toContain('not')
        ->and(strtolower($description))->toContain('instructions');
});
