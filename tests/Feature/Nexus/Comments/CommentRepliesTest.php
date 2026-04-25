<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\Snapshot;
use App\Models\SnapshotVersion;
use App\Models\User;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * @return array{snapshot: Snapshot, version: SnapshotVersion, user: User}
 */
function makeRepliesTarget(): array
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

it('REQ-M6-006: replies relationship orders by created_at ASC', function (): void {
    $base = makeRepliesTarget();

    $root = Comment::factory()
        ->for($base['snapshot'])
        ->create([
            'created_on_version_id' => $base['version']->id,
            'author_user_id' => $base['user']->id,
        ]);

    // Create out of chronological order, then assign explicit timestamps.
    $second = Comment::factory()->reply($root)->create([
        'author_user_id' => $base['user']->id,
        'body' => 'second',
        'created_at' => now()->addMinutes(2),
    ]);
    $first = Comment::factory()->reply($root)->create([
        'author_user_id' => $base['user']->id,
        'body' => 'first',
        'created_at' => now()->addMinute(),
    ]);
    $third = Comment::factory()->reply($root)->create([
        'author_user_id' => $base['user']->id,
        'body' => 'third',
        'created_at' => now()->addMinutes(3),
    ]);

    $bodies = $root->replies()->get()->pluck('body')->all();

    expect($bodies)->toBe(['first', 'second', 'third']);
});

it('REQ-M6-006: roots query scope excludes replies', function (): void {
    $base = makeRepliesTarget();

    $root = Comment::factory()
        ->for($base['snapshot'])
        ->create([
            'created_on_version_id' => $base['version']->id,
            'author_user_id' => $base['user']->id,
        ]);

    $reply = Comment::factory()->reply($root)->create([
        'author_user_id' => $base['user']->id,
    ]);

    $rootIds = Comment::query()->roots()->pluck('id')->all();

    expect($rootIds)->toContain($root->id)
        ->and($rootIds)->not->toContain($reply->id);
});

it('REQ-M6-006: isReply / isRoot accessors reflect parent_comment_id', function (): void {
    $base = makeRepliesTarget();

    $root = Comment::factory()
        ->for($base['snapshot'])
        ->create([
            'created_on_version_id' => $base['version']->id,
            'author_user_id' => $base['user']->id,
        ]);

    $reply = Comment::factory()->reply($root)->create([
        'author_user_id' => $base['user']->id,
    ]);

    expect($root->isRoot())->toBeTrue()
        ->and($root->isReply())->toBeFalse()
        ->and($reply->isRoot())->toBeFalse()
        ->and($reply->isReply())->toBeTrue();
});

it('REQ-M6-006: thread() returns root then replies in created_at order', function (): void {
    $base = makeRepliesTarget();

    $root = Comment::factory()
        ->for($base['snapshot'])
        ->create([
            'created_on_version_id' => $base['version']->id,
            'author_user_id' => $base['user']->id,
            'body' => 'root',
        ]);

    $later = Comment::factory()->reply($root)->create([
        'author_user_id' => $base['user']->id,
        'body' => 'reply-later',
        'created_at' => now()->addMinutes(5),
    ]);

    $earlier = Comment::factory()->reply($root)->create([
        'author_user_id' => $base['user']->id,
        'body' => 'reply-earlier',
        'created_at' => now()->addMinute(),
    ]);

    $thread = $root->thread();

    expect($thread->pluck('body')->all())
        ->toBe(['root', 'reply-earlier', 'reply-later'])
        ->and($thread->first()->is($root))->toBeTrue();
});

it('REQ-M6-006: thread() throws when called on a reply', function (): void {
    $base = makeRepliesTarget();

    $root = Comment::factory()
        ->for($base['snapshot'])
        ->create([
            'created_on_version_id' => $base['version']->id,
            'author_user_id' => $base['user']->id,
        ]);

    $reply = Comment::factory()->reply($root)->create([
        'author_user_id' => $base['user']->id,
    ]);

    expect(fn () => $reply->thread())->toThrow(LogicException::class);
});

it('REQ-M6-006: replies inherit parent anchor — assertion that all anchor cols are null on replies', function (): void {
    $base = makeRepliesTarget();

    $root = Comment::factory()
        ->for($base['snapshot'])
        ->create([
            'created_on_version_id' => $base['version']->id,
            'author_user_id' => $base['user']->id,
        ]);

    $reply = Comment::factory()->reply($root)->create([
        'author_user_id' => $base['user']->id,
    ]);

    $row = DB::table('comments')->where('id', $reply->id)->first();

    expect($row->anchor_quote)->toBeNull()
        ->and($row->anchor_prefix)->toBeNull()
        ->and($row->anchor_suffix)->toBeNull()
        ->and($row->anchor_start_hint)->toBeNull()
        ->and($row->anchor_end_hint)->toBeNull()
        ->and($row->parent_comment_id)->toBe($root->id);
});

it('REQ-M6-006: a reply attempting to set its own anchor is rejected by the DB', function (): void {
    $base = makeRepliesTarget();

    $rootId = DB::table('comments')->insertGetId([
        'snapshot_id' => $base['snapshot']->id,
        'block_id' => Str::uuid()->toString(),
        'parent_comment_id' => null,
        'kind' => 'comment',
        'body' => 'root',
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
    ]);

    expect(fn () => DB::table('comments')->insert([
        'snapshot_id' => $base['snapshot']->id,
        'block_id' => Str::uuid()->toString(),
        'parent_comment_id' => $rootId,
        'kind' => 'comment',
        'body' => 'reply attempting its own anchor',
        'proposed_text' => null,
        'anchor_quote' => 'reply anchor — should be rejected',
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

it('REQ-M6-006: a reply cannot have a reply (depth = 1)', function (): void {
    $base = makeRepliesTarget();

    $rootId = DB::table('comments')->insertGetId([
        'snapshot_id' => $base['snapshot']->id,
        'block_id' => Str::uuid()->toString(),
        'parent_comment_id' => null,
        'kind' => 'comment',
        'body' => 'root',
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
    ]);

    $replyId = DB::table('comments')->insertGetId([
        'snapshot_id' => $base['snapshot']->id,
        'block_id' => Str::uuid()->toString(),
        'parent_comment_id' => $rootId,
        'kind' => 'comment',
        'body' => 'first-level reply',
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
        'body' => 'depth=2 reply, must reject',
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
