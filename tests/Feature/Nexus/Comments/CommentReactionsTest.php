<?php

declare(strict_types=1);

use App\Enums\CommentReactionEmoji;
use App\Models\Comment;
use App\Models\CommentReaction;
use App\Models\Snapshot;
use App\Models\SnapshotVersion;
use App\Models\User;
use App\Models\Workbench;
use App\Nexus\Comments\CommentReactionService;
use App\Nexus\SnapshotVersioning;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * @return array{snapshot: Snapshot, version: SnapshotVersion, user: User}
 */
function makeReactionsTarget(): array
{
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snapshot = Snapshot::factory()->for($workbench)->create();
    $version = SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'report',
        dataPayload: ['blocks' => [
            ['type' => 'markdown', 'body' => 'Anchor target paragraph.'],
        ]],
    );

    return ['snapshot' => $snapshot, 'version' => $version, 'user' => $owner];
}

it('REQ-M6-007: comment_reactions table has expected columns and unique constraint', function (): void {
    expect(Schema::hasTable('comment_reactions'))->toBeTrue();

    $columns = Schema::getColumnListing('comment_reactions');

    expect($columns)->toContain('id', 'comment_id', 'user_id', 'emoji', 'created_at')
        ->and($columns)->not->toContain('updated_at');

    // Unique (comment_id, user_id, emoji) — try inserting a duplicate row.
    $base = makeReactionsTarget();
    $root = Comment::factory()->for($base['snapshot'])->create([
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['user']->id,
    ]);

    DB::table('comment_reactions')->insert([
        'comment_id' => $root->id,
        'user_id' => $base['user']->id,
        'emoji' => CommentReactionEmoji::ThumbsUp->value,
        'created_at' => now(),
    ]);

    expect(fn () => DB::table('comment_reactions')->insert([
        'comment_id' => $root->id,
        'user_id' => $base['user']->id,
        'emoji' => CommentReactionEmoji::ThumbsUp->value,
        'created_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('REQ-M6-007: emoji column rejects values outside the fixed 6-emoji set', function (): void {
    $base = makeReactionsTarget();
    $root = Comment::factory()->for($base['snapshot'])->create([
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['user']->id,
    ]);

    expect(fn () => DB::table('comment_reactions')->insert([
        'comment_id' => $root->id,
        'user_id' => $base['user']->id,
        'emoji' => '🚀',
        'created_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('REQ-M6-007: CommentReactionService::toggle adds a reaction when none exists', function (): void {
    $base = makeReactionsTarget();
    $root = Comment::factory()->for($base['snapshot'])->create([
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['user']->id,
    ]);

    $service = new CommentReactionService;
    $added = $service->toggle($root, $base['user'], CommentReactionEmoji::Heart);

    expect($added)->toBeTrue()
        ->and(CommentReaction::query()->where('comment_id', $root->id)->count())->toBe(1);
});

it('REQ-M6-007: CommentReactionService::toggle removes the reaction when called twice with the same emoji and user', function (): void {
    $base = makeReactionsTarget();
    $root = Comment::factory()->for($base['snapshot'])->create([
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['user']->id,
    ]);

    $service = new CommentReactionService;

    $first = $service->toggle($root, $base['user'], CommentReactionEmoji::Tada);
    $second = $service->toggle($root, $base['user'], CommentReactionEmoji::Tada);

    expect($first)->toBeTrue()
        ->and($second)->toBeFalse()
        ->and(CommentReaction::query()->where('comment_id', $root->id)->count())->toBe(0);
});

it('REQ-M6-007: same user can apply multiple distinct emojis to one comment', function (): void {
    $base = makeReactionsTarget();
    $root = Comment::factory()->for($base['snapshot'])->create([
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['user']->id,
    ]);

    $service = new CommentReactionService;
    $service->toggle($root, $base['user'], CommentReactionEmoji::ThumbsUp);
    $service->toggle($root, $base['user'], CommentReactionEmoji::Eyes);
    $service->toggle($root, $base['user'], CommentReactionEmoji::Thinking);

    expect(CommentReaction::query()->where('comment_id', $root->id)->count())->toBe(3);
});

it('REQ-M6-007: deleting the parent comment cascades to comment_reactions', function (): void {
    $base = makeReactionsTarget();
    $root = Comment::factory()->for($base['snapshot'])->create([
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['user']->id,
    ]);

    (new CommentReactionService)->toggle($root, $base['user'], CommentReactionEmoji::ThumbsDown);

    // Hard-delete via the DB so the cascade fires (the model uses SoftDeletes).
    DB::table('comments')->where('id', $root->id)->delete();

    expect(CommentReaction::query()->where('comment_id', $root->id)->count())->toBe(0);
});

it('REQ-M6-007: reactions can be applied to reply rows as well as roots', function (): void {
    $base = makeReactionsTarget();
    $root = Comment::factory()->for($base['snapshot'])->create([
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['user']->id,
    ]);

    $reply = Comment::factory()->reply($root)->create([
        'author_user_id' => $base['user']->id,
    ]);

    $service = new CommentReactionService;
    $added = $service->toggle($reply, $base['user'], CommentReactionEmoji::Heart);

    expect($added)->toBeTrue()
        ->and($reply->reactions()->count())->toBe(1);
});

it('REQ-M6-007: Comment::reactionsSummary returns emoji => count map', function (): void {
    $base = makeReactionsTarget();
    $root = Comment::factory()->for($base['snapshot'])->create([
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['user']->id,
    ]);

    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $service = new CommentReactionService;
    $service->toggle($root, $base['user'], CommentReactionEmoji::ThumbsUp);
    $service->toggle($root, $alice, CommentReactionEmoji::ThumbsUp);
    $service->toggle($root, $bob, CommentReactionEmoji::Heart);

    $summary = $root->reactionsSummary();

    expect($summary)->toEqualCanonicalizing([
        CommentReactionEmoji::ThumbsUp->value => 2,
        CommentReactionEmoji::Heart->value => 1,
    ]);
});
