<?php

declare(strict_types=1);

use App\Enums\CommentKind;
use App\Models\Comment;
use App\Models\Snapshot;
use App\Models\User;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * REQ-M6-032: the pill's Suggest-edit composer drops the secondary "comment
 * body" textarea — only the proposed_text textarea is rendered. The
 * controller substitutes `''` for the body server-side so the NOT NULL
 * constraint still holds.
 */
it('REQ-M6-032: pill source no longer renders two textareas in suggestion composer mode', function (): void {
    $path = resource_path('js/components/nexus/comment-selection-pill.tsx');

    $source = (string) file_get_contents($path);

    // The composer body for suggestions renders ONLY the proposed_text
    // textarea. The earlier two-textarea layout (comment body + proposed
    // text) is gone — there is no "Why this change?" placeholder, and no
    // separate `<textarea data-testid="comment-selection-pill-body">`
    // visible inside the suggestion branch.
    expect($source)
        ->not->toContain("placeholder={isSuggestion ? 'Why this change?'")
        ->not->toContain("'Why this change?'")
        // The composer keeps a single proposed_text textarea in suggestion
        // mode and a single body textarea in comment mode.
        ->toContain('comment-selection-pill-proposed')
        ->toContain('comment-selection-pill-body')
        // The submit handler forwards an empty body for suggestions.
        ->toContain("body: kind === 'suggestion' ? '' :");
});

it('REQ-M6-032: CommentController@store accepts a suggestion with no body and stores body=""', function (): void {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snapshot = Snapshot::factory()->for($workbench)->create();
    $blockId = Str::uuid()->toString();

    SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'report',
        dataPayload: ['blocks' => [
            ['id' => $blockId, 'type' => 'markdown', 'body' => 'Replace this paragraph.'],
        ]],
    );

    $response = $this->actingAs($owner)->post(
        '/snapshots/'.$snapshot->id.'/comments',
        [
            'block_id' => $blockId,
            'kind' => 'suggestion',
            // body intentionally omitted
            'proposed_text' => 'A better paragraph.',
            'anchor_quote' => 'this paragraph',
            'anchor_prefix' => 'Replace ',
            'anchor_suffix' => '.',
            'anchor_start_hint' => 8,
            'anchor_end_hint' => 22,
        ],
    );

    $response->assertRedirect();

    $comment = Comment::query()->where('snapshot_id', $snapshot->id)->firstOrFail();

    expect($comment->kind)->toBe(CommentKind::Suggestion);
    expect($comment->body)->toBe('');
    expect($comment->proposed_text)->toBe('A better paragraph.');
});
