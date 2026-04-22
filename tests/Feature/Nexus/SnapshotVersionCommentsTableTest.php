<?php

declare(strict_types=1);

use App\Models\Snapshot;
use App\Models\SnapshotCommentAnchor;
use App\Models\SnapshotVersion;
use App\Models\SnapshotVersionComment;
use App\Models\User;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function agentRender6Version(): SnapshotVersion
{
    $workbench = Workbench::factory()->create();
    $snapshot = Snapshot::factory()->for($workbench)->create();

    return SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: [
            'columns' => [['key' => 'id', 'label' => 'ID']],
            'rows' => [['id' => 1]],
        ],
    );
}

it('AGENT-RENDER-6: snapshot_version_comments table exists with expected columns', function (): void {
    $schema = SnapshotVersionComment::query()->getConnection()->getSchemaBuilder();

    expect($schema->hasTable('snapshot_version_comments'))->toBeTrue();
    foreach (['id', 'snapshot_version_id', 'user_id', 'parent_id', 'body', 'resolved_at', 'created_at', 'updated_at', 'deleted_at'] as $column) {
        expect($schema->hasColumn('snapshot_version_comments', $column))->toBeTrue("missing column {$column}");
    }
});

it('AGENT-RENDER-6: snapshot_comment_anchors table exists with expected columns', function (): void {
    $schema = SnapshotCommentAnchor::query()->getConnection()->getSchemaBuilder();

    expect($schema->hasTable('snapshot_comment_anchors'))->toBeTrue();
    foreach (['id', 'comment_id', 'anchor_type', 'anchor_data', 'created_at'] as $column) {
        expect($schema->hasColumn('snapshot_comment_anchors', $column))->toBeTrue("missing column {$column}");
    }
});

it('AGENT-RENDER-6: comment persists with user and version relations', function (): void {
    $version = agentRender6Version();
    $user = User::factory()->create();

    $comment = SnapshotVersionComment::query()->create([
        'snapshot_version_id' => $version->id,
        'user_id' => $user->id,
        'body' => 'first thought',
    ]);

    expect($comment->snapshotVersion->id)->toBe($version->id)
        ->and($comment->user->id)->toBe($user->id)
        ->and($comment->isReply())->toBeFalse()
        ->and($comment->isResolved())->toBeFalse();
});

it('AGENT-RENDER-6: replies self-reference the parent comment', function (): void {
    $version = agentRender6Version();
    $parent = SnapshotVersionComment::factory()
        ->create(['snapshot_version_id' => $version->id]);

    $reply = SnapshotVersionComment::factory()
        ->replyTo($parent)
        ->create();

    expect($reply->isReply())->toBeTrue()
        ->and($reply->parent->id)->toBe($parent->id)
        ->and($parent->replies()->pluck('id')->all())->toBe([$reply->id]);
});

it('AGENT-RENDER-6: resolving a comment sets resolved_at, unresolving clears it', function (): void {
    $version = agentRender6Version();
    $comment = SnapshotVersionComment::factory()
        ->create(['snapshot_version_id' => $version->id]);

    $comment->resolve();
    expect($comment->fresh()->isResolved())->toBeTrue();

    $comment->unresolve();
    expect($comment->fresh()->isResolved())->toBeFalse();
});

it('AGENT-RENDER-6: soft-delete preserves the row', function (): void {
    $version = agentRender6Version();
    $comment = SnapshotVersionComment::factory()
        ->create(['snapshot_version_id' => $version->id]);

    $comment->delete();

    expect(SnapshotVersionComment::query()->whereKey($comment->id)->exists())->toBeFalse()
        ->and(SnapshotVersionComment::withTrashed()->whereKey($comment->id)->exists())->toBeTrue();
});

it('AGENT-RENDER-6: deleting the snapshot version cascades to its comments', function (): void {
    $version = agentRender6Version();
    $comment = SnapshotVersionComment::factory()
        ->create(['snapshot_version_id' => $version->id]);

    $version->snapshot->delete();

    expect(SnapshotVersionComment::withTrashed()->whereKey($comment->id)->exists())->toBeFalse();
});

it('AGENT-RENDER-6: deleting a parent cascades to replies', function (): void {
    $version = agentRender6Version();
    $parent = SnapshotVersionComment::factory()
        ->create(['snapshot_version_id' => $version->id]);
    $reply = SnapshotVersionComment::factory()
        ->replyTo($parent)
        ->create();

    // Cascade is enforced at the DB layer; force-delete to trigger it.
    $parent->forceDelete();

    expect(SnapshotVersionComment::withTrashed()->whereKey($reply->id)->exists())->toBeFalse();
});

it('AGENT-RENDER-6: anchor attaches to a comment with JSON payload', function (): void {
    $version = agentRender6Version();
    $comment = SnapshotVersionComment::factory()
        ->create(['snapshot_version_id' => $version->id]);

    $anchor = SnapshotCommentAnchor::factory()
        ->for($comment, 'comment')
        ->cell(row: 3, column: 'status')
        ->create();

    expect($anchor->anchor_type)->toBe('cell')
        ->and($anchor->anchor_data)->toBe(['row' => 3, 'column' => 'status'])
        ->and($comment->anchors()->pluck('id')->all())->toBe([$anchor->id]);
});

it('AGENT-RENDER-6: deleting a comment cascades to its anchors', function (): void {
    $version = agentRender6Version();
    $comment = SnapshotVersionComment::factory()
        ->create(['snapshot_version_id' => $version->id]);
    $anchor = SnapshotCommentAnchor::factory()->for($comment, 'comment')->create();

    $comment->forceDelete();

    expect(SnapshotCommentAnchor::query()->whereKey($anchor->id)->exists())->toBeFalse();
});

it('AGENT-RENDER-6: user_id is nullable and clears on user delete', function (): void {
    $version = agentRender6Version();
    $user = User::factory()->create();
    $comment = SnapshotVersionComment::factory()
        ->create(['snapshot_version_id' => $version->id, 'user_id' => $user->id]);

    $user->delete();

    expect($comment->fresh()->user_id)->toBeNull();
});

it('AGENT-RENDER-6: parent_id foreign key rejects non-existent parent', function (): void {
    $version = agentRender6Version();

    expect(fn () => SnapshotVersionComment::query()->create([
        'snapshot_version_id' => $version->id,
        'parent_id' => 999_999,
        'body' => 'orphan',
    ]))->toThrow(QueryException::class);
});
