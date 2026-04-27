<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\Snapshot;
use App\Models\SnapshotVersion;
use App\Models\User;
use App\Models\Workbench;
use App\Nexus\Comments\AnchorResolver;
use App\Nexus\Comments\ResolvedAnchor;
use App\Nexus\SnapshotVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Build a snapshot + report version whose payload contains a single
 * markdown block with the supplied id and body. Returns the version.
 */
function reportVersionWithBlock(string $blockId, string $body): SnapshotVersion
{
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snapshot = Snapshot::factory()->for($workbench)->create();

    return SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'report',
        dataPayload: ['blocks' => [
            ['id' => $blockId, 'type' => 'markdown', 'body' => $body],
        ]],
    );
}

function makeAnchorComment(SnapshotVersion $version, string $blockId, array $overrides = []): Comment
{
    return Comment::factory()->make(array_merge([
        'snapshot_id' => $version->snapshot_id,
        'created_on_version_id' => $version->id,
        'block_id' => $blockId,
    ], $overrides));
}

it('REQ-M6-004: opens with hint offsets when the hint window still matches', function () {
    $blockId = (string) Str::uuid();
    $body = 'The quick brown fox jumps over the lazy dog.';
    $version = reportVersionWithBlock($blockId, $body);

    $start = strpos($body, 'brown fox');
    $end = $start + strlen('brown fox');
    $comment = makeAnchorComment($version, $blockId, [
        'anchor_quote' => 'brown fox',
        'anchor_prefix' => 'quick ',
        'anchor_suffix' => ' jumps',
        'anchor_start_hint' => $start,
        'anchor_end_hint' => $end,
    ]);

    $resolved = AnchorResolver::resolve($comment, $version);

    expect($resolved)->toBeInstanceOf(ResolvedAnchor::class)
        ->and($resolved->status)->toBe('open')
        ->and($resolved->blockId)->toBe($blockId)
        ->and($resolved->start)->toBe($start)
        ->and($resolved->end)->toBe($end)
        ->and($resolved->reason)->toBeNull();
});

it('REQ-M6-004: re-locates the quote by substring search when the hint is stale', function () {
    $blockId = (string) Str::uuid();
    // Original body had quote earlier; new body has it shifted right.
    $body = 'A new prefix has been prepended. The quick brown fox jumps.';
    $version = reportVersionWithBlock($blockId, $body);
    $newStart = strpos($body, 'brown fox');

    $comment = makeAnchorComment($version, $blockId, [
        'anchor_quote' => 'brown fox',
        'anchor_prefix' => 'quick ',
        'anchor_suffix' => ' jumps',
        'anchor_start_hint' => 4,   // stale — points at "new "
        'anchor_end_hint' => 13,
    ]);

    $resolved = AnchorResolver::resolve($comment, $version);

    expect($resolved->status)->toBe('open')
        ->and($resolved->start)->toBe($newStart)
        ->and($resolved->end)->toBe($newStart + strlen('brown fox'));
});

it('REQ-M6-004: disambiguates multiple occurrences using prefix/suffix', function () {
    $blockId = (string) Str::uuid();
    $body = 'alpha cat beta cat gamma cat delta';
    $version = reportVersionWithBlock($blockId, $body);

    // Targeting the middle "cat" — preceded by "beta " and followed by " gamma".
    $expectedStart = strpos($body, 'beta cat') + strlen('beta ');

    $comment = makeAnchorComment($version, $blockId, [
        'anchor_quote' => 'cat',
        'anchor_prefix' => 'beta ',
        'anchor_suffix' => ' gamma',
        'anchor_start_hint' => null,
        'anchor_end_hint' => null,
    ]);

    $resolved = AnchorResolver::resolve($comment, $version);

    expect($resolved->status)->toBe('open')
        ->and($resolved->start)->toBe($expectedStart)
        ->and($resolved->end)->toBe($expectedStart + 3);
});

it('REQ-M6-004: returns stale when multiple occurrences remain ambiguous after prefix/suffix', function () {
    $blockId = (string) Str::uuid();
    // Both "cat" occurrences are preceded by "the " and followed by ".".
    $body = 'the cat. the cat.';
    $version = reportVersionWithBlock($blockId, $body);

    $comment = makeAnchorComment($version, $blockId, [
        'anchor_quote' => 'cat',
        'anchor_prefix' => 'the ',
        'anchor_suffix' => '.',
        'anchor_start_hint' => null,
        'anchor_end_hint' => null,
    ]);

    $resolved = AnchorResolver::resolve($comment, $version);

    expect($resolved->status)->toBe('stale')
        ->and($resolved->reason)->toBe('quote ambiguous after disambiguation');
});

it('REQ-M6-004: returns stale when the block id is not in the payload', function () {
    $version = reportVersionWithBlock((string) Str::uuid(), 'unrelated body');

    $comment = makeAnchorComment($version, (string) Str::uuid(), [
        'anchor_quote' => 'whatever',
    ]);

    $resolved = AnchorResolver::resolve($comment, $version);

    expect($resolved->status)->toBe('stale')
        ->and($resolved->reason)->toBe('block missing');
});

it('REQ-M6-004: returns stale when the resolved block is an embed', function () {
    $blockId = (string) Str::uuid();
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snapshot = Snapshot::factory()->for($workbench)->create();
    $embedSnapshot = Snapshot::factory()->for($workbench)->create();

    $version = SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'report',
        dataPayload: ['blocks' => [
            ['id' => $blockId, 'type' => 'embed', 'snapshot_id' => $embedSnapshot->id],
        ]],
    );

    $comment = makeAnchorComment($version, $blockId, [
        'anchor_quote' => 'irrelevant',
    ]);

    $resolved = AnchorResolver::resolve($comment, $version);

    expect($resolved->status)->toBe('stale')
        ->and($resolved->reason)->toBe('block is embed');
});

it('REQ-M6-004: returns stale when the quote no longer occurs in the body', function () {
    $blockId = (string) Str::uuid();
    $version = reportVersionWithBlock($blockId, 'Body has been completely rewritten.');

    $comment = makeAnchorComment($version, $blockId, [
        'anchor_quote' => 'nowhere to be found',
        'anchor_start_hint' => 0,
        'anchor_end_hint' => 19,
    ]);

    $resolved = AnchorResolver::resolve($comment, $version);

    expect($resolved->status)->toBe('stale')
        ->and($resolved->reason)->toBe('quote not found');
});

it('REQ-M6-004: resolves a read-only comment quote across adjacent markdown blocks', function () {
    $firstBlockId = (string) Str::uuid();
    $secondBlockId = (string) Str::uuid();
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snapshot = Snapshot::factory()->for($workbench)->create();

    $version = SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'report',
        dataPayload: ['blocks' => [
            ['id' => $firstBlockId, 'type' => 'markdown', 'body' => 'First block tail'],
            ['id' => $secondBlockId, 'type' => 'markdown', 'body' => 'Second block head'],
        ]],
    );

    $comment = makeAnchorComment($version, $firstBlockId, [
        'kind' => 'comment',
        'anchor_quote' => "block tail\nSecond block",
        'anchor_prefix' => 'First ',
        'anchor_suffix' => ' head',
        'anchor_start_hint' => 6,
        'anchor_end_hint' => 29,
    ]);

    $resolved = AnchorResolver::resolve($comment, $version);

    expect($resolved->status)->toBe('open')
        ->and($resolved->blockId)->toBe($firstBlockId)
        ->and($resolved->start)->toBe(6)
        ->and($resolved->end)->toBe(6 + strlen("block tail\nSecond block"));
});

it('REQ-M6-004: returns stale when the snapshot version is not a report', function () {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snapshot = Snapshot::factory()->for($workbench)->create();
    $version = SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'a', 'label' => 'A']], 'rows' => []],
    );

    $comment = makeAnchorComment($version, (string) Str::uuid(), [
        'anchor_quote' => 'anything',
    ]);

    $resolved = AnchorResolver::resolve($comment, $version);

    expect($resolved->status)->toBe('stale')
        ->and($resolved->reason)->toBe('non-report view');
});

it('REQ-M6-004: does not write to the database during resolution', function () {
    $blockId = (string) Str::uuid();
    $body = 'Quick brown fox.';
    $version = reportVersionWithBlock($blockId, $body);

    $comment = Comment::factory()->create([
        'snapshot_id' => $version->snapshot_id,
        'created_on_version_id' => $version->id,
        'block_id' => $blockId,
        'anchor_quote' => 'brown',
        'anchor_prefix' => 'Quick ',
        'anchor_suffix' => ' fox',
        'anchor_start_hint' => 6,
        'anchor_end_hint' => 11,
    ]);
    $originalUpdatedAt = $comment->updated_at?->toDateTimeString();

    AnchorResolver::resolve($comment->fresh(), $version);

    $reloaded = $comment->fresh();
    expect($reloaded->updated_at?->toDateTimeString())->toBe($originalUpdatedAt)
        ->and($reloaded->status)->toBe($comment->status);
});
