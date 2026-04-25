<?php

declare(strict_types=1);

use App\Enums\CommentStatus;
use App\Models\Comment;
use App\Models\Snapshot;
use App\Models\SnapshotVersion;
use App\Models\User;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * REQ-M6-015: history tab + historical-revision read-only mode.
 *
 * Backend coverage:
 *  - versionHistory prop carries `addressed_comments` per version (id, body
 *    preview, author_kind, status).
 *  - SnapshotController@show signals `is_historical_view` when ?revision= is
 *    older than the snapshot's current version.
 *  - Comments prop resolves anchors against the historical version when
 *    ?revision= is supplied.
 *  - CommentController@store rejects writes when the snapshot has been
 *    advanced beyond the requesting revision.
 *
 * Frontend coverage (file-shape assertions):
 *  - History tab renders one row per version with author icon, summary, and
 *    addressed_comments expansion.
 *  - Clicking a comment id in the history row switches to the comments tab
 *    and scrolls to the anchor.
 *  - Historical view hides the composer and shows a read-only banner.
 */

/**
 * @return array{snapshot: Snapshot, version: SnapshotVersion, owner: User, workbench: Workbench, block_id: string}
 */
function historyReportSetup(string $body = 'The quick brown fox jumps over the lazy dog.', ?string $blockId = null): array
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

it('REQ-M6-015: versionHistory prop includes addressed_comments per version', function (): void {
    $base = historyReportSetup();

    // Push a v2 so we have two revisions to project.
    $v2 = SnapshotVersioning::append(
        snapshot: $base['snapshot'],
        viewType: 'report',
        dataPayload: ['blocks' => [
            ['id' => $base['block_id'], 'type' => 'markdown', 'body' => 'Tightened body for v2.'],
        ]],
        metadata: ['summary' => 'Tightened intro.', 'author_kind' => 'agent'],
    );

    // A comment addressed by v2.
    Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'addressed_on_version_id' => $v2->id,
        'author_user_id' => $base['owner']->id,
        'body' => 'Please tighten this paragraph more aggressively.',
        'anchor_quote' => 'quick brown fox',
        'anchor_prefix' => 'The ',
        'anchor_suffix' => ' jumps',
        'anchor_start_hint' => 4,
        'anchor_end_hint' => 19,
        'status' => CommentStatus::Resolved->value,
    ]);

    $response = $this->actingAs($base['owner'])->get(route('workbench.snapshot.show', [
        'workbench' => $base['workbench']->slug,
        'snapshot' => $base['snapshot']->slug,
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('snapshot')
        ->has('versionHistory', 2)
        ->where('versionHistory.0.revision', 2)
        ->has('versionHistory.0.addressed_comments', 1)
        ->where('versionHistory.0.addressed_comments.0.status', 'resolved')
        ->where('versionHistory.0.addressed_comments.0.author_kind', 'user')
        ->has('versionHistory.0.addressed_comments.0.body_preview')
        ->where('versionHistory.0.is_current', true)
        ->where('versionHistory.1.revision', 1)
        ->has('versionHistory.1.addressed_comments', 0)
        ->where('versionHistory.1.is_current', false),
    );
});

it('REQ-M6-015: addressed_comments only includes comments whose addressed_on_version_id matches', function (): void {
    $base = historyReportSetup();

    $v2 = SnapshotVersioning::append(
        snapshot: $base['snapshot'],
        viewType: 'report',
        dataPayload: ['blocks' => [
            ['id' => $base['block_id'], 'type' => 'markdown', 'body' => 'Body v2.'],
        ]],
    );

    // Addressed-on-v1 (resolved before v2 landed).
    $c1 = Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'addressed_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'body' => 'addressed in v1',
        'anchor_quote' => 'quick brown fox',
        'anchor_prefix' => 'The ',
        'anchor_suffix' => ' jumps',
        'anchor_start_hint' => 4,
        'anchor_end_hint' => 19,
        'status' => CommentStatus::Resolved->value,
    ]);

    // Addressed-on-v2.
    $c2 = Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'addressed_on_version_id' => $v2->id,
        'author_user_id' => $base['owner']->id,
        'body' => 'addressed in v2',
        'anchor_quote' => 'quick brown fox',
        'anchor_prefix' => 'The ',
        'anchor_suffix' => ' jumps',
        'anchor_start_hint' => 4,
        'anchor_end_hint' => 19,
        'status' => CommentStatus::Resolved->value,
    ]);

    // Open on root version, not yet addressed — must NOT appear.
    Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'addressed_on_version_id' => null,
        'author_user_id' => $base['owner']->id,
        'body' => 'still open',
        'anchor_quote' => 'quick brown fox',
        'anchor_prefix' => 'The ',
        'anchor_suffix' => ' jumps',
        'anchor_start_hint' => 4,
        'anchor_end_hint' => 19,
        'status' => CommentStatus::Open->value,
    ]);

    $response = $this->actingAs($base['owner'])->get(route('workbench.snapshot.show', [
        'workbench' => $base['workbench']->slug,
        'snapshot' => $base['snapshot']->slug,
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('snapshot')
        ->has('versionHistory', 2)
        ->where('versionHistory.0.revision', 2)
        ->has('versionHistory.0.addressed_comments', 1)
        ->where('versionHistory.0.addressed_comments.0.id', $c2->id)
        ->where('versionHistory.1.revision', 1)
        ->has('versionHistory.1.addressed_comments', 1)
        ->where('versionHistory.1.addressed_comments.0.id', $c1->id),
    );
});

it('REQ-M6-015: SnapshotController@show passes is_historical_view=true when ?revision is older than current', function (): void {
    $base = historyReportSetup();

    SnapshotVersioning::append(
        snapshot: $base['snapshot'],
        viewType: 'report',
        dataPayload: ['blocks' => [
            ['id' => $base['block_id'], 'type' => 'markdown', 'body' => 'Body v2.'],
        ]],
    );

    // Request v1 explicitly.
    $response = $this->actingAs($base['owner'])->get(route('workbench.snapshot.show', [
        'workbench' => $base['workbench']->slug,
        'snapshot' => $base['snapshot']->slug,
    ]).'?revision=1');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('snapshot')
        ->where('is_historical_view', true)
        ->where('version.revision', 1),
    );

    // And v2 (current) → false.
    $response2 = $this->actingAs($base['owner'])->get(route('workbench.snapshot.show', [
        'workbench' => $base['workbench']->slug,
        'snapshot' => $base['snapshot']->slug,
    ]).'?revision=2');

    $response2->assertOk();
    $response2->assertInertia(fn ($page) => $page
        ->component('snapshot')
        ->where('is_historical_view', false),
    );
});

it('REQ-M6-015: comments prop resolves anchors against the historical version when ?revision is supplied', function (): void {
    $base = historyReportSetup('The quick brown fox jumps over the lazy dog.');

    // A comment anchored to a quote that EXISTS in v1 but is removed in v2.
    Comment::factory()->for($base['snapshot'])->create([
        'block_id' => $base['block_id'],
        'created_on_version_id' => $base['version']->id,
        'author_user_id' => $base['owner']->id,
        'body' => 'I love this phrase.',
        'anchor_quote' => 'quick brown fox',
        'anchor_prefix' => 'The ',
        'anchor_suffix' => ' jumps',
        'anchor_start_hint' => 4,
        'anchor_end_hint' => 19,
        'status' => CommentStatus::Open->value,
    ]);

    // v2 removes the anchor text entirely.
    SnapshotVersioning::append(
        snapshot: $base['snapshot'],
        viewType: 'report',
        dataPayload: ['blocks' => [
            ['id' => $base['block_id'], 'type' => 'markdown', 'body' => 'Replaced entirely.'],
        ]],
    );

    // Viewing the historical revision: anchor must resolve against v1, so the
    // comment shows resolved_in_current_version=true (relative to v1).
    $response = $this->actingAs($base['owner'])->get(route('workbench.snapshot.show', [
        'workbench' => $base['workbench']->slug,
        'snapshot' => $base['snapshot']->slug,
    ]).'?revision=1');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('snapshot')
        ->has('comments', 1)
        ->where('comments.0.anchor.resolved_in_current_version', true),
    );
});

it('REQ-M6-015: CommentController@store rejects writes when the snapshot has been advanced beyond the requesting revision', function (): void {
    $base = historyReportSetup();
    $v1Id = $base['version']->id;

    // Advance to v2.
    SnapshotVersioning::append(
        snapshot: $base['snapshot'],
        viewType: 'report',
        dataPayload: ['blocks' => [
            ['id' => $base['block_id'], 'type' => 'markdown', 'body' => 'Body v2.'],
        ]],
    );

    // Posting with expected_version_id = v1 (stale) → 409.
    $stale = $this->actingAs($base['owner'])->post('/snapshots/'.$base['snapshot']->id.'/comments', [
        'block_id' => $base['block_id'],
        'kind' => 'comment',
        'body' => 'late to the party',
        'anchor_quote' => 'quick brown fox',
        'anchor_prefix' => 'The ',
        'anchor_suffix' => ' jumps',
        'anchor_start_hint' => 4,
        'anchor_end_hint' => 19,
        'expected_version_id' => $v1Id,
    ]);

    $stale->assertStatus(409);
    expect(Comment::query()->where('snapshot_id', $base['snapshot']->id)->count())->toBe(0);

    // Posting against current version still works.
    $current = $this->actingAs($base['owner'])->post('/snapshots/'.$base['snapshot']->id.'/comments', [
        'block_id' => $base['block_id'],
        'kind' => 'comment',
        'body' => 'on time',
        'anchor_quote' => 'Body v2',
        'anchor_prefix' => '',
        'anchor_suffix' => '.',
        'anchor_start_hint' => 0,
        'anchor_end_hint' => 7,
        'expected_version_id' => (int) $base['snapshot']->fresh()->current_version_id,
    ]);

    $current->assertRedirect();
    expect(Comment::query()->where('snapshot_id', $base['snapshot']->id)->count())->toBe(1);
});

it('REQ-M6-015: history tab renders one row per version with author icon, summary, addressed_comments', function (): void {
    $path = resource_path('js/components/nexus/snapshot-sidebar.tsx');

    expect(file_exists($path))->toBeTrue();

    $source = (string) file_get_contents($path);

    expect($source)
        // Type extended with addressed_comments + is_current.
        ->toContain('addressed_comments')
        ->toContain('is_current')
        ->toContain('body_preview')
        // Per-version row test id used by the new history rendering.
        ->toContain('snapshot-sidebar-history-entry')
        // Expand caret carries an aria-expanded toggle.
        ->toContain('aria-expanded')
        // Relative time formatter (Intl.RelativeTimeFormat as fallback).
        ->toContain('Intl.RelativeTimeFormat')
        // View this version → version-switcher-style URL with ?revision=.
        ->toContain('?revision=');
});

it('REQ-M6-015: clicking a comment id in the history row switches to comments tab and scrolls anchor', function (): void {
    $path = resource_path('js/components/nexus/snapshot-sidebar.tsx');
    $source = (string) file_get_contents($path);

    expect($source)
        // Click on an addressed-comment item should call scrollToAndHighlightAnchor.
        ->toContain('scrollToAndHighlightAnchor')
        // History row's addressed comment list test id.
        ->toContain('snapshot-sidebar-history-addressed-comment')
        // Switching tab on click — references setActiveTab('comments').
        ->toContain("setActiveTab('comments')");
});

it('REQ-M6-015: historical view hides the composer and shows a read-only banner', function (): void {
    $sidebarPath = resource_path('js/components/nexus/snapshot-sidebar.tsx');
    $sidebarSource = (string) file_get_contents($sidebarPath);

    expect($sidebarSource)
        // New prop wired through.
        ->toContain('isHistoricalView')
        // Read-only banner copy.
        ->toContain('Viewing historical revision')
        ->toContain('Read-only')
        // Banner test id.
        ->toContain('snapshot-sidebar-historical-banner');

    $reportPath = resource_path('js/components/nexus/report-view.tsx');
    $reportSource = (string) file_get_contents($reportPath);

    expect($reportSource)
        // report-view passes isHistoricalView through to the sidebar.
        ->toContain('isHistoricalView');
});
