<?php

declare(strict_types=1);

use App\Enums\CommentAuthorKind;
use App\Enums\CommentKind;
use App\Enums\CommentResolution;
use App\Enums\CommentStatus;
use App\Models\Comment;
use App\Models\Snapshot;
use App\Models\SnapshotVersion;
use App\Models\User;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Helper: spin up a snapshot with one report revision so tests can plug a
 * valid (snapshot_id, created_on_version_id) pair into raw inserts.
 *
 * @return array{snapshot: Snapshot, version: SnapshotVersion, user: User}
 */
function makeTargetSnapshot(): array
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

/**
 * Insert a row directly so CHECK/trigger violations surface as QueryException
 * rather than being intercepted by Eloquent casts or fillable filtering.
 *
 * @param  array<string, mixed>  $overrides
 */
function rawInsertComment(array $overrides = []): int
{
    $base = makeTargetSnapshot();

    return DB::table('comments')->insertGetId(array_merge([
        'snapshot_id' => $base['snapshot']->id,
        'block_id' => Str::uuid()->toString(),
        'parent_comment_id' => null,
        'kind' => 'comment',
        'body' => 'A root comment.',
        'proposed_text' => null,
        'anchor_quote' => 'Anchor target paragraph.',
        'anchor_prefix' => '',
        'anchor_suffix' => '',
        'anchor_start_hint' => 0,
        'anchor_end_hint' => 24,
        'status' => 'open',
        'resolution' => null,
        'created_on_version_id' => $base['version']->id,
        'addressed_on_version_id' => null,
        'author_user_id' => $base['user']->id,
        'author_kind' => 'user',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

it('REQ-M6-003: comments table has all required columns with correct types', function (): void {
    expect(Schema::hasTable('comments'))->toBeTrue();

    foreach ([
        'id',
        'snapshot_id',
        'block_id',
        'parent_comment_id',
        'kind',
        'body',
        'proposed_text',
        'anchor_quote',
        'anchor_prefix',
        'anchor_suffix',
        'anchor_start_hint',
        'anchor_end_hint',
        'status',
        'resolution',
        'created_on_version_id',
        'addressed_on_version_id',
        'author_user_id',
        'author_kind',
        'created_at',
        'updated_at',
        'deleted_at',
    ] as $column) {
        expect(Schema::hasColumn('comments', $column))->toBeTrue("missing column {$column}");
    }

    // block_id is a uuid — Postgres reports the type as 'uuid'.
    $blockType = Schema::getColumnType('comments', 'block_id');
    expect($blockType)->toBe('uuid');
});

it('REQ-M6-003: comments enforces reply CHECK — replies cannot have anchor fields', function (): void {
    $base = makeTargetSnapshot();
    $rootId = rawInsertComment(['snapshot_id' => $base['snapshot']->id, 'created_on_version_id' => $base['version']->id, 'author_user_id' => $base['user']->id]);

    expect(fn () => DB::table('comments')->insert([
        'snapshot_id' => $base['snapshot']->id,
        'block_id' => Str::uuid()->toString(),
        'parent_comment_id' => $rootId,
        'kind' => 'comment',
        'body' => 'A reply with a stray anchor.',
        'proposed_text' => null,
        'anchor_quote' => 'illegal anchor',
        'anchor_prefix' => null,
        'anchor_suffix' => null,
        'anchor_start_hint' => null,
        'anchor_end_hint' => null,
        'status' => 'open',
        'resolution' => null,
        'created_on_version_id' => $base['version']->id,
        'addressed_on_version_id' => null,
        'author_user_id' => $base['user']->id,
        'author_kind' => 'user',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('REQ-M6-003: comments enforces reply CHECK — replies cannot be kind=suggestion', function (): void {
    $base = makeTargetSnapshot();
    $rootId = rawInsertComment(['snapshot_id' => $base['snapshot']->id, 'created_on_version_id' => $base['version']->id, 'author_user_id' => $base['user']->id]);

    expect(fn () => DB::table('comments')->insert([
        'snapshot_id' => $base['snapshot']->id,
        'block_id' => Str::uuid()->toString(),
        'parent_comment_id' => $rootId,
        'kind' => 'suggestion',
        'body' => 'A reply masquerading as a suggestion.',
        'proposed_text' => 'replacement',
        'anchor_quote' => null,
        'anchor_prefix' => null,
        'anchor_suffix' => null,
        'anchor_start_hint' => null,
        'anchor_end_hint' => null,
        'status' => 'open',
        'resolution' => null,
        'created_on_version_id' => $base['version']->id,
        'addressed_on_version_id' => null,
        'author_user_id' => $base['user']->id,
        'author_kind' => 'user',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('REQ-M6-003: comments enforces suggestion CHECK — kind=suggestion requires proposed_text', function (): void {
    expect(fn () => rawInsertComment([
        'kind' => 'suggestion',
        'proposed_text' => null,
    ]))->toThrow(QueryException::class);
});

it('REQ-M6-003: comments enforces suggestion CHECK — kind=comment forbids proposed_text', function (): void {
    expect(fn () => rawInsertComment([
        'kind' => 'comment',
        'proposed_text' => 'should not be set',
    ]))->toThrow(QueryException::class);
});

it('REQ-M6-003: comments enforces depth=1 — replies cannot have replies', function (): void {
    $base = makeTargetSnapshot();
    $rootId = rawInsertComment(['snapshot_id' => $base['snapshot']->id, 'created_on_version_id' => $base['version']->id, 'author_user_id' => $base['user']->id]);

    $replyId = DB::table('comments')->insertGetId([
        'snapshot_id' => $base['snapshot']->id,
        'block_id' => Str::uuid()->toString(),
        'parent_comment_id' => $rootId,
        'kind' => 'comment',
        'body' => 'A first-level reply.',
        'proposed_text' => null,
        'anchor_quote' => null,
        'anchor_prefix' => null,
        'anchor_suffix' => null,
        'anchor_start_hint' => null,
        'anchor_end_hint' => null,
        'status' => 'open',
        'resolution' => null,
        'created_on_version_id' => $base['version']->id,
        'addressed_on_version_id' => null,
        'author_user_id' => $base['user']->id,
        'author_kind' => 'user',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => DB::table('comments')->insert([
        'snapshot_id' => $base['snapshot']->id,
        'block_id' => Str::uuid()->toString(),
        'parent_comment_id' => $replyId,
        'kind' => 'comment',
        'body' => 'A nested reply that must be rejected.',
        'proposed_text' => null,
        'anchor_quote' => null,
        'anchor_prefix' => null,
        'anchor_suffix' => null,
        'anchor_start_hint' => null,
        'anchor_end_hint' => null,
        'status' => 'open',
        'resolution' => null,
        'created_on_version_id' => $base['version']->id,
        'addressed_on_version_id' => null,
        'author_user_id' => $base['user']->id,
        'author_kind' => 'user',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('REQ-M6-003: Comment model casts enums correctly', function (): void {
    $base = makeTargetSnapshot();

    $root = Comment::factory()
        ->for($base['snapshot'])
        ->create([
            'created_on_version_id' => $base['version']->id,
            'author_user_id' => $base['user']->id,
        ]);

    expect($root->kind)->toBe(CommentKind::Comment)
        ->and($root->status)->toBe(CommentStatus::Open)
        ->and($root->resolution)->toBeNull()
        ->and($root->author_kind)->toBe(CommentAuthorKind::User);

    $root->forceFill([
        'status' => CommentStatus::Resolved->value,
        'resolution' => CommentResolution::Agent->value,
    ])->save();

    expect($root->fresh()->status)->toBe(CommentStatus::Resolved)
        ->and($root->fresh()->resolution)->toBe(CommentResolution::Agent);
});

it('REQ-M6-003: deleting a snapshot cascades to its comments', function (): void {
    $base = makeTargetSnapshot();

    $comment = Comment::factory()
        ->for($base['snapshot'])
        ->create([
            'created_on_version_id' => $base['version']->id,
            'author_user_id' => $base['user']->id,
        ]);

    expect(DB::table('comments')->where('id', $comment->id)->exists())->toBeTrue();

    $base['snapshot']->delete();

    expect(DB::table('comments')->where('id', $comment->id)->exists())->toBeFalse();
});

it('REQ-M6-003: SoftDeletes excludes deleted comments from default queries', function (): void {
    $base = makeTargetSnapshot();

    $comment = Comment::factory()
        ->for($base['snapshot'])
        ->create([
            'created_on_version_id' => $base['version']->id,
            'author_user_id' => $base['user']->id,
        ]);

    $comment->delete();

    expect(Comment::query()->whereKey($comment->id)->exists())->toBeFalse()
        ->and(Comment::withTrashed()->whereKey($comment->id)->exists())->toBeTrue()
        ->and(DB::table('comments')->where('id', $comment->id)->whereNotNull('deleted_at')->exists())->toBeTrue();
});
