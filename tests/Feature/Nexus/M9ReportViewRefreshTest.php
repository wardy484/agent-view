<?php

declare(strict_types=1);

use App\Models\Snapshot;
use App\Models\User;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * REQ-M9-010 — Refresh `components/nexus/report-view.tsx`: prose styling
 * delegates to the REQ-M9-004 `.prose` utility, comment indicator styling
 * rebuilt with new tokens, suggestion gutter polished. Every existing block
 * `id` is carried forward verbatim — the M6 stable-anchor contract is
 * non-negotiable.
 */
function reportViewSource(): string
{
    return file_get_contents(resource_path('js/components/nexus/report-view.tsx'));
}

function m9CommentHighlightOverlaySource(): string
{
    return file_get_contents(resource_path('js/components/nexus/comment-highlight-overlay.tsx'));
}

it('REQ-M9-010: report-view imports shadcn primitives for the embed chrome', function () {
    $src = reportViewSource();

    expect($src)
        ->toContain("from '@/components/ui/badge'")
        ->toContain("from '@/components/ui/button'")
        ->toContain("from '@/components/ui/card'");
});

it('REQ-M9-010: report-view uses no arbitrary text/radius/shadow brackets', function () {
    $src = reportViewSource();

    expect(preg_match('/text-\[\d+(\.\d+)?px\]/', $src))
        ->toBe(0, 'report-view.tsx must use the M9 type tokens, not text-[NNpx] arbitrary values.');

    expect(preg_match('/rounded-\[[^\]]+\]/', $src))
        ->toBe(0, 'report-view.tsx must use rounded-md/lg/sm, not rounded-[…].');

    expect(preg_match('/shadow-\[[^\]]+\]/', $src))
        ->toBe(0, 'report-view.tsx must not use arbitrary shadow brackets.');
});

it('REQ-M9-010: report-view drops nx-* legacy utility classes', function () {
    $src = reportViewSource();

    expect($src)
        ->not->toContain('nx-card')
        ->not->toContain('nx-btn')
        ->not->toContain('nx-stage-header');
});

it('REQ-M9-010: report-view delegates prose styling to the M9 .prose utility', function () {
    $src = reportViewSource();

    // REQ-M9-004 wired the .prose class onto the outer wrapper; this REQ
    // must not unwire it. We accept either a literal `prose` token in a
    // className string or inside cn(...).
    expect($src)->toMatch('/[\'"`]prose\b/');
});

it('REQ-M9-010: report-view preserves the M6 anchor contract on markdown blocks', function () {
    $src = reportViewSource();

    // The M6 highlight overlay walks `[data-comment-block-id="…"]` to find
    // each block, then queries `data-block-type` to gate behaviour. If
    // either disappears every existing comment goes stale on next render.
    expect($src)
        ->toContain('data-comment-block-id={id}')
        ->toContain('data-block-type="markdown"')
        ->toContain('data-block-type="embed"');
});

it('REQ-M9-010: report-view carries forward block ids verbatim through the markdown branch', function () {
    $src = reportViewSource();

    // The dispatcher must thread the optional `block.id` through the
    // MarkdownBlockView so the section's data-comment-block-id mirrors the
    // server-assigned id one-to-one. Renaming or recomputing the id here
    // would silently shift every comment anchor on the next render.
    expect($src)->toContain('id={block.id}');
});

it('REQ-M9-010: comment-highlight-overlay still emits data-comment-id / data-status / data-kind', function () {
    $src = m9CommentHighlightOverlaySource();

    // These are the attributes the click handler, the tooltip and the
    // (read-only) snapshot tests for M6 all assert on. The REQ-M9-010
    // refresh is style-only; the contract MUST survive verbatim.
    expect($src)
        ->toContain("'data-comment-id'")
        ->toContain("'data-status'")
        ->toContain("'data-kind'");
});

it('REQ-M9-010: comment-highlight-overlay keeps the three REQ-M6-038 wrap triggers', function () {
    $src = m9CommentHighlightOverlaySource();

    // (a) immediate sync wrap, (b) requestAnimationFrame retry, (c) 250ms
    // poll. The MutationObserver re-trigger must also remain. If any of
    // these drop out, comment highlights will silently fail to paint.
    expect($src)
        ->toContain('requestAnimationFrame')
        ->toContain('setTimeout')
        ->toContain('MutationObserver');
});

it('REQ-M9-010: comment-highlight-overlay uses the M9 ring token for hover affordance', function () {
    $src = m9CommentHighlightOverlaySource();

    // The hover ring previously hard-coded `ring-amber-400`. M9 ties the
    // overlay's interactive affordance to the shared `--ring` token so it
    // tracks the rest of the design system.
    expect($src)
        ->toContain('hover:ring-ring')
        ->not->toContain('hover:ring-amber-400');
});

it('REQ-M9-010: comment-highlight-overlay uses no arbitrary brackets', function () {
    $src = m9CommentHighlightOverlaySource();

    expect(preg_match('/text-\[\d+(\.\d+)?px\]/', $src))->toBe(0);
    expect(preg_match('/rounded-\[[^\]]+\]/', $src))->toBe(0);
    expect(preg_match('/shadow-\[[^\]]+\]/', $src))->toBe(0);
});

it('REQ-M9-010: report-view JSX heading/button text uses Title Case', function () {
    $src = reportViewSource();

    $tagPattern = '<(h1|h2|h3|Button)[^>\n]*>\s*([a-z])';
    preg_match_all('/'.$tagPattern.'/m', $src, $matches, PREG_SET_ORDER);

    $offenders = array_map(
        fn ($m) => '<'.$m[1].'> starts with "'.$m[2].'"',
        $matches,
    );

    expect($offenders)->toBe(
        [],
        'report-view.tsx contains lowercase JSX heading/button text — fix to Title Case: '
            .implode(', ', $offenders),
    );
});

it('REQ-M9-010: snapshot route still renders the report view end-to-end', function () {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snapshot = Snapshot::factory()->for($workbench)->create();

    $blockId = (string) Str::uuid();

    SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'report',
        dataPayload: [
            'blocks' => [
                [
                    'type' => 'markdown',
                    'id' => $blockId,
                    'body' => "# Heading\n\nBody text.",
                ],
            ],
        ],
    );

    actingAs($owner)
        ->withoutVite()
        ->get("/workbenches/{$workbench->slug}/snapshots/{$snapshot->slug}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('snapshot')
            ->where('snapshot.slug', $snapshot->slug)
            ->where('version.view_type', 'report')
            ->etc()
        );
});

it('REQ-M9-010: server-side block-id carry-forward keeps existing ids stable across revisions', function () {
    // The M6 anchor contract is non-negotiable: when an agent rewrites
    // a block payload but supplies the same `id`, the snapshot version
    // service MUST keep that id so the comment overlay still resolves.
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snapshot = Snapshot::factory()->for($workbench)->create();

    $blockId = (string) Str::uuid();

    $v1 = SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'report',
        dataPayload: [
            'blocks' => [
                ['type' => 'markdown', 'id' => $blockId, 'body' => 'first'],
            ],
        ],
    );

    $v2 = SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'report',
        dataPayload: [
            'blocks' => [
                ['type' => 'markdown', 'id' => $blockId, 'body' => 'second'],
            ],
        ],
    );

    $payload1 = $v1->data_payload;
    $payload2 = $v2->data_payload;

    expect($payload1['blocks'][0]['id'])->toBe($blockId);
    expect($payload2['blocks'][0]['id'])->toBe($blockId);
});
