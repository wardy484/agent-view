<?php

declare(strict_types=1);

use App\Enums\CommentReactionEmoji;
use App\Enums\CommentStatus;
use App\Mcp\Servers\NexusServer;
use App\Mcp\Tools\GetSnapshotComments;
use App\Models\Comment;
use App\Models\Snapshot;
use App\Models\SnapshotVersion;
use App\Models\User;
use App\Models\Workbench;
use App\Nexus\Comments\CommentReactionService;
use App\Nexus\SnapshotVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Build a snapshot owned by $owner whose first revision is a single
 * markdown block. Returns the [snapshot, version, owner, blockId].
 *
 * @return array{snapshot: Snapshot, version: SnapshotVersion, owner: User, block_id: string}
 */
function makeReportSnapshot(?User $owner = null, string $body = 'Anchor target paragraph.'): array
{
    $owner ??= User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snapshot = Snapshot::factory()->for($workbench)->create();
    $blockId = Str::uuid()->toString();
    $version = SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'report',
        dataPayload: ['blocks' => [
            ['id' => $blockId, 'type' => 'markdown', 'body' => $body],
        ]],
    );

    return ['snapshot' => $snapshot, 'version' => $version, 'owner' => $owner, 'block_id' => $blockId];
}

it('REQ-M6-009: returns open comments by default for a snapshot the caller can view', function (): void {
    $base = makeReportSnapshot();

    Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'body' => 'open one',
        'anchor_quote' => 'Anchor target paragraph.',
        'anchor_prefix' => '',
        'anchor_suffix' => '',
        'anchor_start_hint' => 0,
        'anchor_end_hint' => strlen('Anchor target paragraph.'),
        'status' => CommentStatus::Open->value,
    ]);

    Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'body' => 'resolved one',
        'anchor_quote' => 'Anchor target paragraph.',
        'status' => CommentStatus::Resolved->value,
    ]);

    Sanctum::actingAs($base['owner']);

    NexusServer::tool(GetSnapshotComments::class, [
        'snapshot_id' => $base['snapshot']->id,
    ])->assertOk()
        ->assertStructuredContent(function (AssertableJson $json) use ($base): void {
            $json->where('snapshot_id', $base['snapshot']->id)
                ->where('current_revision', $base['version']->revision)
                ->has('comments', 1)
                ->where('comments.0.body', 'open one')
                ->where('comments.0.status', 'open')
                ->etc();
        });
});

it('REQ-M6-009: resolves comments when snapshot_id is a workbench snapshot URL', function (): void {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create([
        'owner_user_id' => $owner->id,
        'slug' => 'tutora-pr-10181-qualification-plan-review',
    ]);
    $base = makeReportSnapshot($owner);
    $base['snapshot']->forceFill([
        'workbench_id' => $workbench->id,
        'slug' => '01KQ6DHBJXXF8MDKGSAHTPQES5',
    ])->save();

    Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $owner->id,
        'body' => 'prod URL comment',
        'anchor_quote' => 'Anchor target paragraph.',
        'status' => CommentStatus::Open->value,
    ]);

    Sanctum::actingAs($owner);

    NexusServer::tool(GetSnapshotComments::class, [
        'snapshot_id' => 'https://nexus-ui-production-w1o1a4.laravel.cloud/workbenches/tutora-pr-10181-qualification-plan-review/snapshots/01KQ6DHBJXXF8MDKGSAHTPQES5',
    ])->assertOk()
        ->assertStructuredContent(function (AssertableJson $json) use ($base): void {
            $json->where('snapshot_id', $base['snapshot']->id)
                ->has('comments', 1)
                ->where('comments.0.body', 'prod URL comment')
                ->etc();
        });
});

it('REQ-M6-009: status=all returns every non-deleted comment regardless of state', function (): void {
    $base = makeReportSnapshot();

    foreach (['open', 'resolved', 'stale', 'wontfix'] as $s) {
        Comment::factory()->for($base['snapshot'])->create([
            'block_id' => $base['block_id'],
            'created_on_version_id' => $base['version']->id,
            'author_user_id' => $base['owner']->id,
            'status' => $s,
            'anchor_quote' => 'Anchor target paragraph.',
        ]);
    }

    Sanctum::actingAs($base['owner']);

    NexusServer::tool(GetSnapshotComments::class, [
        'snapshot_id' => $base['snapshot']->id,
        'status' => 'all',
    ])->assertOk()
        ->assertStructuredContent(function (AssertableJson $json): void {
            $json->has('comments', 4)->etc();
        });
});

it('REQ-M6-009: status=resolved filters to resolved-only', function (): void {
    $base = makeReportSnapshot();

    Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'status' => CommentStatus::Open->value,
        'anchor_quote' => 'Anchor target paragraph.',
    ]);
    Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'body' => 'r',
        'status' => CommentStatus::Resolved->value,
        'anchor_quote' => 'Anchor target paragraph.',
    ]);

    Sanctum::actingAs($base['owner']);

    NexusServer::tool(GetSnapshotComments::class, [
        'snapshot_id' => $base['snapshot']->id,
        'status' => 'resolved',
    ])->assertOk()
        ->assertStructuredContent(function (AssertableJson $json): void {
            $json->has('comments', 1)
                ->where('comments.0.status', 'resolved')
                ->where('comments.0.body', 'r')
                ->etc();
        });
});

it('REQ-M6-009: include_resolved_since adds recently-resolved comments to the open set', function (): void {
    $base = makeReportSnapshot();

    Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'body' => 'still-open',
        'status' => CommentStatus::Open->value,
        'anchor_quote' => 'Anchor target paragraph.',
    ]);

    $oldResolved = Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'body' => 'old-resolved',
        'status' => CommentStatus::Resolved->value,
        'anchor_quote' => 'Anchor target paragraph.',
    ]);
    $oldResolved->forceFill(['updated_at' => now()->subDays(2)])->save();

    $recentResolved = Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'body' => 'recent-resolved',
        'status' => CommentStatus::Resolved->value,
        'anchor_quote' => 'Anchor target paragraph.',
    ]);
    $recentResolved->forceFill(['updated_at' => now()->subMinute()])->save();

    Sanctum::actingAs($base['owner']);

    NexusServer::tool(GetSnapshotComments::class, [
        'snapshot_id' => $base['snapshot']->id,
        'status' => 'open',
        'include_resolved_since' => now()->subHour()->toIso8601String(),
    ])->assertOk()
        ->assertStructuredContent(function (AssertableJson $json): void {
            $json->has('comments', 2)
                ->where('comments.0.body', 'still-open')
                ->where('comments.1.body', 'recent-resolved')
                ->etc();
        });
});

it('REQ-M6-009: anchor.resolved_in_current_version reflects AnchorResolver outcome', function (): void {
    $base = makeReportSnapshot();

    Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'body' => 'will-go-stale',
        'status' => CommentStatus::Open->value,
        'anchor_quote' => 'completely missing quote',
        'anchor_prefix' => '',
        'anchor_suffix' => '',
        'anchor_start_hint' => null,
        'anchor_end_hint' => null,
    ]);

    Sanctum::actingAs($base['owner']);

    // REQ-M6-012: lazy stale updater fires before the read, so a comment
    // whose anchor no longer resolves flips status=open → stale and would
    // be filtered out by the default status=open. Pass status=all to see it.
    NexusServer::tool(GetSnapshotComments::class, [
        'snapshot_id' => $base['snapshot']->id,
        'status' => 'all',
    ])->assertOk()
        ->assertStructuredContent(function (AssertableJson $json): void {
            $json->has('comments', 1)
                ->where('comments.0.anchor.resolved_in_current_version', false)
                ->etc();
        });
});

it('REQ-M6-009: thread is ordered ASC by created_at and excludes the root', function (): void {
    $base = makeReportSnapshot();

    $root = Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'body' => 'root',
        'anchor_quote' => 'Anchor target paragraph.',
    ]);

    Comment::factory()->reply($root)->create([
        'author_user_id' => $base['owner']->id,
        'body' => 'second',
        'created_at' => now()->addMinutes(2),
    ]);
    Comment::factory()->reply($root)->create([
        'author_user_id' => $base['owner']->id,
        'body' => 'first',
        'created_at' => now()->addMinute(),
    ]);
    Comment::factory()->reply($root)->create([
        'author_user_id' => $base['owner']->id,
        'body' => 'third',
        'created_at' => now()->addMinutes(3),
    ]);

    Sanctum::actingAs($base['owner']);

    NexusServer::tool(GetSnapshotComments::class, [
        'snapshot_id' => $base['snapshot']->id,
    ])->assertOk()
        ->assertStructuredContent(function (AssertableJson $json): void {
            $json->has('comments', 1)
                ->where('comments.0.body', 'root')
                ->has('comments.0.thread', 3)
                ->where('comments.0.thread.0.body', 'first')
                ->where('comments.0.thread.1.body', 'second')
                ->where('comments.0.thread.2.body', 'third')
                ->etc();
        });
});

it('REQ-M6-009: reactions_summary aggregates emoji counts across users', function (): void {
    $base = makeReportSnapshot();

    $root = Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'anchor_quote' => 'Anchor target paragraph.',
    ]);

    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $service = new CommentReactionService;
    $service->toggle($root, $base['owner'], CommentReactionEmoji::ThumbsUp);
    $service->toggle($root, $alice, CommentReactionEmoji::ThumbsUp);
    $service->toggle($root, $bob, CommentReactionEmoji::Heart);

    Sanctum::actingAs($base['owner']);

    NexusServer::tool(GetSnapshotComments::class, [
        'snapshot_id' => $base['snapshot']->id,
    ])->assertOk()
        ->assertStructuredContent(function (AssertableJson $json): void {
            $json->has('comments', 1)
                ->where('comments.0.reactions_summary.'.CommentReactionEmoji::ThumbsUp->value, 2)
                ->where('comments.0.reactions_summary.'.CommentReactionEmoji::Heart->value, 1)
                ->etc();
        });
});

it('REQ-M6-009: kind=suggestion comments include proposed_text in output', function (): void {
    $base = makeReportSnapshot();

    Comment::factory()
        ->for($base['snapshot'])
        ->suggestion('the new text')
        ->create([
            'block_id' => $base['block_id'],
            'created_on_version_id' => $base['version']->id,
            'author_user_id' => $base['owner']->id,
            'anchor_quote' => 'Anchor target paragraph.',
        ]);

    Sanctum::actingAs($base['owner']);

    NexusServer::tool(GetSnapshotComments::class, [
        'snapshot_id' => $base['snapshot']->id,
    ])->assertOk()
        ->assertStructuredContent(function (AssertableJson $json): void {
            $json->has('comments', 1)
                ->where('comments.0.kind', 'suggestion')
                ->where('comments.0.proposed_text', 'the new text')
                ->etc();
        });
});

it('REQ-M6-009: caller without view permission is denied', function (): void {
    $base = makeReportSnapshot();

    Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'anchor_quote' => 'Anchor target paragraph.',
    ]);

    $stranger = User::factory()->create();
    Sanctum::actingAs($stranger);

    NexusServer::tool(GetSnapshotComments::class, [
        'snapshot_id' => $base['snapshot']->id,
    ])->assertHasErrors(['You are not authorised to view this snapshot.']);
});

it('REQ-M6-009: deleted (soft-deleted) comments are not returned', function (): void {
    $base = makeReportSnapshot();

    $alive = Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'body' => 'alive',
        'anchor_quote' => 'Anchor target paragraph.',
    ]);

    $dead = Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'body' => 'dead',
        'anchor_quote' => 'Anchor target paragraph.',
    ]);
    $dead->delete();

    Sanctum::actingAs($base['owner']);

    NexusServer::tool(GetSnapshotComments::class, [
        'snapshot_id' => $base['snapshot']->id,
        'status' => 'all',
    ])->assertOk()
        ->assertStructuredContent(function (AssertableJson $json) use ($alive): void {
            $json->has('comments', 1)
                ->where('comments.0.id', (int) $alive->id)
                ->etc();
        });
});

it('REQ-M6-009: tool description contains the user-data-not-instructions warning', function (): void {
    $reflector = new ReflectionClass(GetSnapshotComments::class);
    $attributes = $reflector->getAttributes(Description::class);

    expect($attributes)->not->toBeEmpty();

    $description = (string) $attributes[0]->newInstance()->value;

    expect($description)->toContain('user-authored data')
        ->and($description)->toContain('not')
        ->and(strtolower($description))->toContain('instructions');
});
