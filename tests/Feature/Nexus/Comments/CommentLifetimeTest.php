<?php

declare(strict_types=1);

use App\Enums\CommentResolution;
use App\Enums\CommentStatus;
use App\Mcp\Servers\NexusServer;
use App\Mcp\Tools\GetSnapshotComments;
use App\Models\Comment;
use App\Models\Snapshot;
use App\Models\SnapshotVersion;
use App\Models\User;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Build a report snapshot whose first revision is a single markdown block.
 *
 * @return array{snapshot: Snapshot, version: SnapshotVersion, owner: User, workbench: Workbench, block_id: string}
 */
function lifetimeReportSetup(string $body = 'The quick brown fox jumps.', ?string $blockId = null): array
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

it('REQ-M6-012: appending a new SnapshotVersion does not copy comment rows', function (): void {
    $base = lifetimeReportSetup('Original body with quote target here.');

    $comment = Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'anchor_quote' => 'quote target',
        'anchor_prefix' => 'with ',
        'anchor_suffix' => ' here',
        'anchor_start_hint' => strpos('Original body with quote target here.', 'quote target'),
        'anchor_end_hint' => strpos('Original body with quote target here.', 'quote target') + strlen('quote target'),
        'status' => CommentStatus::Open->value,
    ]);

    SnapshotVersioning::append(
        snapshot: $base['snapshot'],
        viewType: 'report',
        dataPayload: ['blocks' => [
            ['id' => $base['block_id'], 'type' => 'markdown', 'body' => 'Revised body with quote target retained here.'],
        ]],
    );

    expect(Comment::query()->where('snapshot_id', $base['snapshot']->id)->count())->toBe(1);

    $reloaded = $comment->fresh();
    expect($reloaded->id)->toBe($comment->id)
        ->and((int) $reloaded->created_on_version_id)->toBe((int) $base['version']->id);
});

it('REQ-M6-012: GetSnapshotComments updates an open comment to stale when its quote is no longer in the current revision', function (): void {
    $base = lifetimeReportSetup('The quick brown fox jumps.');

    $comment = Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'anchor_quote' => 'brown fox',
        'anchor_prefix' => 'quick ',
        'anchor_suffix' => ' jumps',
        'anchor_start_hint' => strpos('The quick brown fox jumps.', 'brown fox'),
        'anchor_end_hint' => strpos('The quick brown fox jumps.', 'brown fox') + strlen('brown fox'),
        'status' => CommentStatus::Open->value,
    ]);

    // Drop the quoted text in a new revision.
    SnapshotVersioning::append(
        snapshot: $base['snapshot'],
        viewType: 'report',
        dataPayload: ['blocks' => [
            ['id' => $base['block_id'], 'type' => 'markdown', 'body' => 'Completely rewritten body without the quote.'],
        ]],
    );

    Sanctum::actingAs($base['owner']);

    NexusServer::tool(GetSnapshotComments::class, [
        'snapshot_id' => $base['snapshot']->id,
        'status' => 'all',
    ])->assertOk();

    expect($comment->fresh()->status)->toBe(CommentStatus::Stale);
});

it('REQ-M6-012: GetSnapshotComments auto-revives a stale comment when a later revision re-introduces the quote', function (): void {
    $base = lifetimeReportSetup('The quick brown fox jumps.');

    // Comment is already stale (anchor manually fabricated to not match).
    $comment = Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'anchor_quote' => 'silver eagle',
        'anchor_prefix' => '',
        'anchor_suffix' => '',
        'anchor_start_hint' => null,
        'anchor_end_hint' => null,
        'status' => CommentStatus::Stale->value,
    ]);

    // A later revision re-introduces the quote.
    SnapshotVersioning::append(
        snapshot: $base['snapshot'],
        viewType: 'report',
        dataPayload: ['blocks' => [
            ['id' => $base['block_id'], 'type' => 'markdown', 'body' => 'A wild silver eagle has landed.'],
        ]],
    );

    Sanctum::actingAs($base['owner']);

    NexusServer::tool(GetSnapshotComments::class, [
        'snapshot_id' => $base['snapshot']->id,
        'status' => 'all',
    ])->assertOk();

    expect($comment->fresh()->status)->toBe(CommentStatus::Open);
});

it('REQ-M6-012: terminal status (resolved) is not flipped to stale by the lazy updater', function (): void {
    $base = lifetimeReportSetup('The quick brown fox jumps.');

    $comment = Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'anchor_quote' => 'brown fox',
        'anchor_prefix' => 'quick ',
        'anchor_suffix' => ' jumps',
        'anchor_start_hint' => strpos('The quick brown fox jumps.', 'brown fox'),
        'anchor_end_hint' => strpos('The quick brown fox jumps.', 'brown fox') + strlen('brown fox'),
        'status' => CommentStatus::Resolved->value,
        'resolution' => CommentResolution::User->value,
    ]);

    // Quote disappears in a new revision — would normally turn open→stale.
    SnapshotVersioning::append(
        snapshot: $base['snapshot'],
        viewType: 'report',
        dataPayload: ['blocks' => [
            ['id' => $base['block_id'], 'type' => 'markdown', 'body' => 'Totally different content here.'],
        ]],
    );

    Sanctum::actingAs($base['owner']);

    NexusServer::tool(GetSnapshotComments::class, [
        'snapshot_id' => $base['snapshot']->id,
        'status' => 'all',
    ])->assertOk();

    expect($comment->fresh()->status)->toBe(CommentStatus::Resolved);
});

it('REQ-M6-012: terminal status (wontfix) is not flipped to open by the lazy updater', function (): void {
    $base = lifetimeReportSetup('A wild silver eagle has landed.');

    // Wontfix comment with an anchor that DOES resolve in the current
    // revision — auto-revive must NOT flip it.
    $comment = Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'anchor_quote' => 'silver eagle',
        'anchor_prefix' => 'wild ',
        'anchor_suffix' => ' has',
        'anchor_start_hint' => strpos('A wild silver eagle has landed.', 'silver eagle'),
        'anchor_end_hint' => strpos('A wild silver eagle has landed.', 'silver eagle') + strlen('silver eagle'),
        'status' => CommentStatus::Wontfix->value,
        'resolution' => CommentResolution::User->value,
    ]);

    Sanctum::actingAs($base['owner']);

    NexusServer::tool(GetSnapshotComments::class, [
        'snapshot_id' => $base['snapshot']->id,
        'status' => 'all',
    ])->assertOk();

    expect($comment->fresh()->status)->toBe(CommentStatus::Wontfix);
});

it('REQ-M6-012: created_on_version_id cannot be mutated after creation', function (): void {
    $base = lifetimeReportSetup();

    $comment = Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'anchor_quote' => 'The',
    ]);

    // Append a second revision so we have a different version_id to assign.
    $newVersion = SnapshotVersioning::append(
        snapshot: $base['snapshot'],
        viewType: 'report',
        dataPayload: ['blocks' => [
            ['id' => $base['block_id'], 'type' => 'markdown', 'body' => 'Revised body.'],
        ]],
    );

    expect(fn () => tap($comment, function (Comment $c) use ($newVersion): void {
        $c->created_on_version_id = $newVersion->id;
        $c->save();
    }))->toThrow(LogicException::class, 'Comment::created_on_version_id is immutable');

    // The on-disk row is unchanged.
    expect((int) $comment->fresh()->created_on_version_id)->toBe((int) $base['version']->id);
});

it('REQ-M6-012: SnapshotController@show on a report runs the lazy updater', function (): void {
    $base = lifetimeReportSetup('The quick brown fox jumps.');

    $comment = Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'anchor_quote' => 'brown fox',
        'anchor_prefix' => 'quick ',
        'anchor_suffix' => ' jumps',
        'anchor_start_hint' => strpos('The quick brown fox jumps.', 'brown fox'),
        'anchor_end_hint' => strpos('The quick brown fox jumps.', 'brown fox') + strlen('brown fox'),
        'status' => CommentStatus::Open->value,
    ]);

    // Drop the quoted text in a new revision.
    SnapshotVersioning::append(
        snapshot: $base['snapshot'],
        viewType: 'report',
        dataPayload: ['blocks' => [
            ['id' => $base['block_id'], 'type' => 'markdown', 'body' => 'Body without the quoted text.'],
        ]],
    );

    $this->actingAs($base['owner'])
        ->get(route('workbench.snapshot.show', [
            'workbench' => $base['workbench']->slug,
            'snapshot' => $base['snapshot']->slug,
        ]))
        ->assertOk();

    expect($comment->fresh()->status)->toBe(CommentStatus::Stale);
});

it('REQ-M6-012: SnapshotController@show on a non-report view does not invoke the lazy updater', function (): void {
    // Snapshot with a non-report current revision. We seed a comment whose
    // anchor would resolve as stale (non-report view), but the controller
    // should skip the updater entirely.
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snapshot = Snapshot::factory()->for($workbench)->create();
    $blockId = Str::uuid()->toString();

    // Synthetic: bootstrap a report v1 so the comment can be filed against
    // it (the constraints require created_on_version_id), then append a
    // table revision so the snapshot's currentVersion is non-report.
    $reportVersion = SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'report',
        dataPayload: ['blocks' => [
            ['id' => $blockId, 'type' => 'markdown', 'body' => 'Initial body for anchoring.'],
        ]],
    );

    $comment = Comment::factory()->for($snapshot)->create([
        'block_id' => $blockId,
        'created_on_version_id' => $reportVersion->id,
        'author_user_id' => $owner->id,
        'anchor_quote' => 'Initial',
        'anchor_prefix' => '',
        'anchor_suffix' => ' body',
        'anchor_start_hint' => 0,
        'anchor_end_hint' => strlen('Initial'),
        'status' => CommentStatus::Open->value,
    ]);

    SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'a', 'label' => 'A']], 'rows' => []],
    );

    $this->actingAs($owner)
        ->get(route('workbench.snapshot.show', [
            'workbench' => $workbench->slug,
            'snapshot' => $snapshot->slug,
        ]))
        ->assertOk();

    // No staleness flip — the controller skipped the updater because the
    // current revision is a table view.
    expect($comment->fresh()->status)->toBe(CommentStatus::Open);
});
