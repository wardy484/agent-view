<?php

declare(strict_types=1);

use App\Enums\SnapshotVisibility;
use App\Models\Comment;
use App\Models\Snapshot;
use App\Models\SnapshotShare;
use App\Models\User;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use App\Policies\CommentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function commentPolicy(): CommentPolicy
{
    return app(CommentPolicy::class);
}

/**
 * Build a report-typed snapshot owned by $owner with one block. Returns the
 * fresh model so visibility/version data is settled.
 */
function seedReportSnapshot(User $owner, SnapshotVisibility $visibility = SnapshotVisibility::Private): Snapshot
{
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snapshot = Snapshot::factory()->for($workbench)->create();
    SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'report',
        dataPayload: ['blocks' => [
            ['type' => 'markdown', 'body' => 'Anchor target.'],
        ]],
    );
    if ($visibility !== SnapshotVisibility::Private) {
        $snapshot->setVisibility($visibility);
    }

    return $snapshot->fresh(['workbench']);
}

it('REQ-M6-005: view mirrors SnapshotPolicy view — owner can view', function (): void {
    $owner = User::factory()->create();
    $snapshot = seedReportSnapshot($owner);
    $comment = Comment::factory()
        ->for($snapshot)
        ->create(['author_user_id' => $owner->id]);

    expect(commentPolicy()->view($owner, $comment))->toBeTrue();
});

it('REQ-M6-005: view mirrors SnapshotPolicy view — share grantee can view', function (): void {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $snapshot = seedReportSnapshot($owner, SnapshotVisibility::Shared);
    SnapshotShare::factory()
        ->for($snapshot)
        ->forUser($invitee)
        ->create(['granted_by_user_id' => $owner->id]);

    $comment = Comment::factory()
        ->for($snapshot)
        ->create(['author_user_id' => $owner->id]);

    expect(commentPolicy()->view($invitee, $comment))->toBeTrue();
});

it('REQ-M6-005: view mirrors SnapshotPolicy view — outsider cannot view', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $snapshot = seedReportSnapshot($owner);
    $comment = Comment::factory()
        ->for($snapshot)
        ->create(['author_user_id' => $owner->id]);

    expect(commentPolicy()->view($stranger, $comment))->toBeFalse();
});

it('REQ-M6-005: create allowed for snapshot owner', function (): void {
    $owner = User::factory()->create();
    $snapshot = seedReportSnapshot($owner);

    expect(commentPolicy()->create($owner, $snapshot))->toBeTrue();
});

it('REQ-M6-005: create allowed for active snapshot_shares grantee', function (): void {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $snapshot = seedReportSnapshot($owner, SnapshotVisibility::Shared);
    SnapshotShare::factory()
        ->for($snapshot)
        ->forUser($invitee)
        ->create(['granted_by_user_id' => $owner->id]);

    expect(commentPolicy()->create($invitee, $snapshot))->toBeTrue();
});

it('REQ-M6-005: create allowed for active share matched by email only', function (): void {
    $owner = User::factory()->create();
    $invitee = User::factory()->create(['email' => 'ada@example.com']);
    $snapshot = seedReportSnapshot($owner, SnapshotVisibility::Shared);
    SnapshotShare::query()->create([
        'snapshot_id' => $snapshot->id,
        'email' => 'ADA@Example.COM',
        'user_id' => null,
        'granted_by_user_id' => $owner->id,
    ]);

    expect(commentPolicy()->create($invitee, $snapshot))->toBeTrue();
});

it('REQ-M6-005: create denied for revoked share grantee', function (): void {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $snapshot = seedReportSnapshot($owner, SnapshotVisibility::Shared);
    SnapshotShare::factory()
        ->for($snapshot)
        ->forUser($invitee)
        ->revoked()
        ->create(['granted_by_user_id' => $owner->id]);

    expect(commentPolicy()->create($invitee, $snapshot))->toBeFalse();
});

it('REQ-M6-005: create denied for outsider', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $snapshot = seedReportSnapshot($owner);

    expect(commentPolicy()->create($stranger, $snapshot))->toBeFalse();
});

it('REQ-M6-005: update allowed for original author', function (): void {
    $owner = User::factory()->create();
    $author = User::factory()->create();
    $snapshot = seedReportSnapshot($owner, SnapshotVisibility::Shared);
    SnapshotShare::factory()
        ->for($snapshot)
        ->forUser($author)
        ->create(['granted_by_user_id' => $owner->id]);
    $comment = Comment::factory()
        ->for($snapshot)
        ->create(['author_user_id' => $author->id]);

    expect(commentPolicy()->update($author, $comment))->toBeTrue();
});

it('REQ-M6-005: update allowed for snapshot owner (mod power)', function (): void {
    $owner = User::factory()->create();
    $author = User::factory()->create();
    $snapshot = seedReportSnapshot($owner, SnapshotVisibility::Shared);
    $comment = Comment::factory()
        ->for($snapshot)
        ->create(['author_user_id' => $author->id]);

    expect(commentPolicy()->update($owner, $comment))->toBeTrue();
});

it('REQ-M6-005: update denied for unrelated viewer', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $snapshot = seedReportSnapshot($owner);
    $comment = Comment::factory()
        ->for($snapshot)
        ->create(['author_user_id' => $owner->id]);

    expect(commentPolicy()->update($stranger, $comment))->toBeFalse();
});

it('REQ-M6-005: update denied on agent-authored comments even for owner', function (): void {
    $owner = User::factory()->create();
    $snapshot = seedReportSnapshot($owner);
    $comment = Comment::factory()
        ->for($snapshot)
        ->asAgent()
        ->create(['author_user_id' => null]);

    expect(commentPolicy()->update($owner, $comment))->toBeFalse();
});

it('REQ-M6-005: delete follows the same rules as update', function (): void {
    $owner = User::factory()->create();
    $author = User::factory()->create();
    $stranger = User::factory()->create();
    $snapshot = seedReportSnapshot($owner, SnapshotVisibility::Shared);
    SnapshotShare::factory()
        ->for($snapshot)
        ->forUser($author)
        ->create(['granted_by_user_id' => $owner->id]);
    $userComment = Comment::factory()
        ->for($snapshot)
        ->create(['author_user_id' => $author->id]);
    $agentComment = Comment::factory()
        ->for($snapshot)
        ->asAgent()
        ->create(['author_user_id' => null]);

    expect(commentPolicy()->delete($author, $userComment))->toBeTrue()
        ->and(commentPolicy()->delete($owner, $userComment))->toBeTrue()
        ->and(commentPolicy()->delete($stranger, $userComment))->toBeFalse()
        ->and(commentPolicy()->delete($owner, $agentComment))->toBeFalse()
        ->and(commentPolicy()->delete($author, $agentComment))->toBeFalse();
});

it('REQ-M6-005: resolve allowed on agent-authored comments by snapshot owner', function (): void {
    $owner = User::factory()->create();
    $snapshot = seedReportSnapshot($owner);
    $agentComment = Comment::factory()
        ->for($snapshot)
        ->asAgent()
        ->create(['author_user_id' => null]);

    expect(commentPolicy()->resolve($owner, $agentComment))->toBeTrue();
});

it('REQ-M6-005: resolve allowed for original author of user comment', function (): void {
    $owner = User::factory()->create();
    $author = User::factory()->create();
    $snapshot = seedReportSnapshot($owner, SnapshotVisibility::Shared);
    SnapshotShare::factory()
        ->for($snapshot)
        ->forUser($author)
        ->create(['granted_by_user_id' => $owner->id]);
    $comment = Comment::factory()
        ->for($snapshot)
        ->create(['author_user_id' => $author->id]);

    expect(commentPolicy()->resolve($author, $comment))->toBeTrue();
});

it('REQ-M6-005: resolve denied for unrelated viewer', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $snapshot = seedReportSnapshot($owner, SnapshotVisibility::Shared);
    SnapshotShare::factory()
        ->for($snapshot)
        ->forUser($stranger)
        ->create(['granted_by_user_id' => $owner->id]);
    $comment = Comment::factory()
        ->for($snapshot)
        ->create(['author_user_id' => $owner->id]);

    expect(commentPolicy()->resolve($stranger, $comment))->toBeFalse();
});

it('REQ-M6-005: react allowed for any viewer', function (): void {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $stranger = User::factory()->create();
    $snapshot = seedReportSnapshot($owner, SnapshotVisibility::Shared);
    SnapshotShare::factory()
        ->for($snapshot)
        ->forUser($invitee)
        ->create(['granted_by_user_id' => $owner->id]);
    $comment = Comment::factory()
        ->for($snapshot)
        ->create(['author_user_id' => $owner->id]);

    expect(commentPolicy()->react($owner, $comment))->toBeTrue()
        ->and(commentPolicy()->react($invitee, $comment))->toBeTrue()
        ->and(commentPolicy()->react($stranger, $comment))->toBeFalse();
});
