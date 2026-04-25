<?php

declare(strict_types=1);

use App\Enums\CommentKind;
use App\Enums\CommentResolution;
use App\Enums\CommentStatus;
use App\Models\Comment;
use App\Models\Snapshot;
use App\Models\SnapshotVersion;
use App\Models\User;
use App\Models\Workbench;
use App\Nexus\Comments\StaleAnchorException;
use App\Nexus\Comments\SuggestionAcceptanceService;
use App\Nexus\SnapshotVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Build an owned report snapshot whose current revision contains a single
 * markdown block (with a stable UUID id) so suggestions can anchor cleanly.
 *
 * @return array{0: Snapshot, 1: User, 2: string}
 */
function suggestionSetup(?string $body = null, ?string $blockId = null): array
{
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snapshot = Snapshot::factory()->for($workbench)->create();
    $blockId ??= Str::uuid()->toString();
    $body ??= 'The quick brown fox jumps over the lazy dog.';

    SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'report',
        dataPayload: ['blocks' => [
            ['id' => $blockId, 'type' => 'markdown', 'body' => $body],
        ]],
    );

    return [$snapshot->fresh(['workbench', 'currentVersion']), $owner, $blockId];
}

it('REQ-M6-008: accept replaces anchor_quote with proposed_text in a new revision', function (): void {
    [$snapshot, $owner, $blockId] = suggestionSetup('The quick brown fox jumps over the lazy dog.');
    $version = $snapshot->currentVersion;
    $comment = Comment::factory()
        ->for($snapshot)
        ->suggestion('slow red')
        ->create([
            'block_id' => $blockId,
            'created_on_version_id' => $version->id,
            'author_user_id' => $owner->id,
            'anchor_quote' => 'quick brown',
            'anchor_prefix' => 'The ',
            'anchor_suffix' => ' fox',
            'anchor_start_hint' => 4,
            'anchor_end_hint' => 15,
        ]);

    $newVersion = app(SuggestionAcceptanceService::class)->accept($comment, $owner);

    expect($newVersion->revision)->toBe($version->revision + 1);
    $blocks = $newVersion->data_payload['blocks'];
    expect($blocks[0]['body'])->toBe('The slow red fox jumps over the lazy dog.');
    expect($snapshot->fresh()->current_version_id)->toBe($newVersion->id);
});

it('REQ-M6-008: accept atomically sets comment status=resolved, resolution=user, addressed_on_version_id', function (): void {
    [$snapshot, $owner, $blockId] = suggestionSetup();
    $comment = Comment::factory()
        ->for($snapshot)
        ->suggestion('slow red')
        ->create([
            'block_id' => $blockId,
            'created_on_version_id' => $snapshot->current_version_id,
            'author_user_id' => $owner->id,
            'anchor_quote' => 'quick brown',
            'anchor_prefix' => 'The ',
            'anchor_suffix' => ' fox',
            'anchor_start_hint' => 4,
            'anchor_end_hint' => 15,
        ]);

    $newVersion = app(SuggestionAcceptanceService::class)->accept($comment, $owner);

    $comment->refresh();
    expect($comment->status)->toBe(CommentStatus::Resolved);
    expect($comment->resolution)->toBe(CommentResolution::User);
    expect($comment->addressed_on_version_id)->toBe($newVersion->id);
});

it('REQ-M6-008: accept on a stale anchor returns 409 and does not create a new revision', function (): void {
    [$snapshot, $owner, $blockId] = suggestionSetup('Some unrelated text here.');
    $beforeRevision = $snapshot->currentVersion->revision;
    $comment = Comment::factory()
        ->for($snapshot)
        ->suggestion('slow red')
        ->create([
            'block_id' => $blockId,
            'created_on_version_id' => $snapshot->current_version_id,
            'author_user_id' => $owner->id,
            'anchor_quote' => 'quick brown',
            'anchor_prefix' => 'The ',
            'anchor_suffix' => ' fox',
            'anchor_start_hint' => 4,
            'anchor_end_hint' => 15,
        ]);

    $response = $this->actingAs($owner)
        ->postJson("/snapshots/{$snapshot->id}/comments/{$comment->id}/accept");

    $response->assertStatus(409)
        ->assertJson(['error' => 'stale_anchor']);
    $response->assertJsonPath('reason', 'quote not found');

    expect(SnapshotVersion::query()->where('snapshot_id', $snapshot->id)->max('revision'))
        ->toBe($beforeRevision);

    $comment->refresh();
    expect($comment->status)->toBe(CommentStatus::Open);
    expect($comment->addressed_on_version_id)->toBeNull();
});

it('REQ-M6-008: accept rejects non-owner users with 403', function (): void {
    [$snapshot, $owner, $blockId] = suggestionSetup();
    $stranger = User::factory()->create();
    $comment = Comment::factory()
        ->for($snapshot)
        ->suggestion('slow red')
        ->create([
            'block_id' => $blockId,
            'created_on_version_id' => $snapshot->current_version_id,
            'author_user_id' => $owner->id,
            'anchor_quote' => 'quick brown',
            'anchor_prefix' => 'The ',
            'anchor_suffix' => ' fox',
            'anchor_start_hint' => 4,
            'anchor_end_hint' => 15,
        ]);

    $response = $this->actingAs($stranger)
        ->postJson("/snapshots/{$snapshot->id}/comments/{$comment->id}/accept");

    $response->assertStatus(403);

    $comment->refresh();
    expect($comment->status)->toBe(CommentStatus::Open);
});

it('REQ-M6-008: accept refuses non-suggestion comments', function (): void {
    [$snapshot, $owner, $blockId] = suggestionSetup();
    $comment = Comment::factory()
        ->for($snapshot)
        ->create([
            'kind' => CommentKind::Comment->value,
            'proposed_text' => null,
            'block_id' => $blockId,
            'created_on_version_id' => $snapshot->current_version_id,
            'author_user_id' => $owner->id,
            'anchor_quote' => 'quick brown',
            'anchor_prefix' => 'The ',
            'anchor_suffix' => ' fox',
            'anchor_start_hint' => 4,
            'anchor_end_hint' => 15,
        ]);

    $response = $this->actingAs($owner)
        ->postJson("/snapshots/{$snapshot->id}/comments/{$comment->id}/accept");

    $response->assertStatus(422)
        ->assertJsonPath('error', 'invalid_comment_kind');
});

it('REQ-M6-008: new revision carries forward block ids per REQ-M6-001', function (): void {
    [$snapshot, $owner, $blockId] = suggestionSetup();
    $comment = Comment::factory()
        ->for($snapshot)
        ->suggestion('slow red')
        ->create([
            'block_id' => $blockId,
            'created_on_version_id' => $snapshot->current_version_id,
            'author_user_id' => $owner->id,
            'anchor_quote' => 'quick brown',
            'anchor_prefix' => 'The ',
            'anchor_suffix' => ' fox',
            'anchor_start_hint' => 4,
            'anchor_end_hint' => 15,
        ]);

    $newVersion = app(SuggestionAcceptanceService::class)->accept($comment, $owner);

    expect($newVersion->data_payload['blocks'][0]['id'])->toBe($blockId);
});

it('REQ-M6-008: failure inside the transaction does not partially apply (atomicity)', function (): void {
    [$snapshot, $owner, $blockId] = suggestionSetup();
    $beforeRevision = $snapshot->currentVersion->revision;
    $comment = Comment::factory()
        ->for($snapshot)
        ->suggestion('slow red')
        ->create([
            'block_id' => $blockId,
            'created_on_version_id' => $snapshot->current_version_id,
            'author_user_id' => $owner->id,
            'anchor_quote' => 'quick brown',
            'anchor_prefix' => 'The ',
            'anchor_suffix' => ' fox',
            'anchor_start_hint' => 4,
            'anchor_end_hint' => 15,
        ]);

    // Simulate a downstream failure by mutating the block id immediately
    // before acceptance so AnchorResolver returns stale (block missing).
    $version = $snapshot->currentVersion;
    $payload = $version->data_payload;
    $payload['blocks'][0]['id'] = Str::uuid()->toString();
    SnapshotVersion::query()->whereKey($version->id)->update(['data_payload' => json_encode($payload)]);

    expect(fn () => app(SuggestionAcceptanceService::class)->accept($comment, $owner))
        ->toThrow(StaleAnchorException::class);

    expect(SnapshotVersion::query()->where('snapshot_id', $snapshot->id)->max('revision'))
        ->toBe($beforeRevision);

    $comment->refresh();
    expect($comment->status)->toBe(CommentStatus::Open);
    expect($comment->addressed_on_version_id)->toBeNull();
});
