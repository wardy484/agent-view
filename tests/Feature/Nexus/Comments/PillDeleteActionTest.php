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
 * REQ-M6-031: the floating pill grows a fourth icon-only Delete action
 * (Trash2) that fires off a `kind = suggestion` POST with `proposed_text = ''`
 * (empty string represents deletion of the selected text). Cross-block
 * selections disable Delete because the acceptance path still replaces text
 * inside one source markdown block.
 */
it('REQ-M6-031: comment-selection-pill ships a Delete button with the Trash2 icon', function (): void {
    $path = resource_path('js/components/nexus/comment-selection-pill.tsx');

    expect(file_exists($path))->toBeTrue();

    $source = (string) file_get_contents($path);

    expect($source)
        // Trash2 icon is imported from lucide-react.
        ->toContain('Trash2')
        ->toContain("from 'lucide-react'")
        // Pill button declared with the expected label + testId.
        ->toContain('label="Delete"')
        ->toContain('comment-selection-pill-delete')
        ->toContain('icon={<Trash2 className="size-4"')
        // Cross-block + read-only disable Delete the same way Comment +
        // Suggest are disabled.
        ->toContain('disabled={crossesBlocks || readOnly}')
        // The Delete handler exists and posts kind=suggestion + proposed_text=''
        ->toContain('handleDelete')
        ->toContain("kind: 'suggestion'")
        ->toContain("proposed_text: ''");
});

it('REQ-M6-031: CommentController@store accepts kind=suggestion with proposed_text=""', function (): void {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snapshot = Snapshot::factory()->for($workbench)->create();
    $blockId = Str::uuid()->toString();

    SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'report',
        dataPayload: ['blocks' => [
            ['id' => $blockId, 'type' => 'markdown', 'body' => 'The quick brown fox.'],
        ]],
    );

    $response = $this->actingAs($owner)->post(
        '/snapshots/'.$snapshot->id.'/comments',
        [
            'block_id' => $blockId,
            'kind' => 'suggestion',
            'body' => '',
            'proposed_text' => '',
            'anchor_quote' => 'quick brown fox',
            'anchor_prefix' => 'The ',
            'anchor_suffix' => '.',
            'anchor_start_hint' => 4,
            'anchor_end_hint' => 19,
        ],
    );

    $response->assertRedirect();

    $comment = Comment::query()->where('snapshot_id', $snapshot->id)->first();

    expect($comment)->not->toBeNull();
    expect($comment->kind)->toBe(CommentKind::Suggestion);
    expect($comment->proposed_text)->toBe('');
    expect($comment->body)->toBe('');
});
