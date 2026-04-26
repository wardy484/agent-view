<?php

declare(strict_types=1);

use App\Models\Snapshot;
use App\Models\User;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * REQ-M9-011 — Rebuild the visual chrome of the four custom view components
 * (`table-view.tsx`, `kanban-view.tsx`, `flowchart-view.tsx`,
 * `slide-deck-view.tsx`) against the new tokens (radius, spacing, shadow,
 * font scale). Functional behaviour is unchanged. A kanban column placed
 * next to a shadcn `<Card>` must feel visually contiguous.
 */
function tableViewSource(): string
{
    return file_get_contents(resource_path('js/components/nexus/table-view.tsx'));
}

function kanbanViewSource(): string
{
    return file_get_contents(resource_path('js/components/nexus/kanban-view.tsx'));
}

function flowchartViewSource(): string
{
    return file_get_contents(resource_path('js/components/nexus/flowchart-view.tsx'));
}

function slideDeckViewSource(): string
{
    return file_get_contents(resource_path('js/components/nexus/slide-deck-view.tsx'));
}

/** @return array<int, string> */
function customViewSources(): array
{
    return [
        'table-view.tsx' => tableViewSource(),
        'kanban-view.tsx' => kanbanViewSource(),
        'flowchart-view.tsx' => flowchartViewSource(),
        'slide-deck-view.tsx' => slideDeckViewSource(),
    ];
}

it('REQ-M9-011: table-view imports shadcn Card / Button / Badge primitives', function () {
    $src = tableViewSource();

    expect($src)
        ->toContain("from '@/components/ui/card'")
        ->toContain("from '@/components/ui/button'")
        ->toContain("from '@/components/ui/badge'")
        ->toContain('<Card')
        ->toContain('<Button')
        ->toContain('<Badge');
});

it('REQ-M9-011: kanban-view wraps each column in a shadcn Card so it sits flush next to other Cards', function () {
    $src = kanbanViewSource();

    expect($src)
        ->toContain("from '@/components/ui/card'")
        ->toContain('<Card');
});

it('REQ-M9-011: flowchart-view frames the diagram inside a shadcn Card', function () {
    $src = flowchartViewSource();

    expect($src)
        ->toContain("from '@/components/ui/card'")
        ->toContain('<Card');
});

it('REQ-M9-011: slide-deck-view uses shadcn Button primitives for prev/next controls', function () {
    $src = slideDeckViewSource();

    expect($src)
        ->toContain("from '@/components/ui/button'")
        ->toContain('<Button');
});

it('REQ-M9-011: custom views drop the legacy nx-* utility classes', function () {
    foreach (customViewSources() as $file => $src) {
        expect($src)
            ->not->toContain('nx-card', "{$file} still mentions nx-card")
            ->not->toContain('nx-btn', "{$file} still mentions nx-btn")
            ->not->toContain('nx-stage-header', "{$file} still mentions nx-stage-header")
            ->not->toContain('nx-stage-title', "{$file} still mentions nx-stage-title")
            ->not->toContain('nx-view-chip', "{$file} still mentions nx-view-chip");
    }
});

it('REQ-M9-011: custom views use no arbitrary text/radius/shadow brackets', function () {
    foreach (customViewSources() as $file => $src) {
        expect(preg_match('/text-\[\d+(\.\d+)?px\]/', $src))
            ->toBe(0, "{$file} must use the M9 text tokens, not text-[NNpx] arbitrary values.");

        expect(preg_match('/rounded-\[[^\]]+\]/', $src))
            ->toBe(0, "{$file} must use rounded-md/lg/sm, not rounded-[…] arbitrary values.");

        expect(preg_match('/shadow-\[[^\]]+\]/', $src))
            ->toBe(0, "{$file} must not use arbitrary shadow brackets.");
    }
});

it('REQ-M9-011: kanban-view preserves the M7-006 link/status/assignee contract', function () {
    $src = kanbanViewSource();

    // Anchor pattern for link_url cards (REQ-M7-006).
    expect($src)
        ->toContain('href={card.link_url}')
        ->toContain('target="_blank"')
        ->toContain('rel="noopener noreferrer"');

    // The 4 px coloured stripe semantics for ok/warn/error.
    expect($src)
        ->toContain("'border-l-4 border-l-emerald-500'")
        ->toContain("'border-l-4 border-l-amber-500'")
        ->toContain("'border-l-4 border-l-red-500'");

    // Assignee chip still present.
    expect($src)
        ->toContain('card.assignee')
        ->toContain('data-testid="nexus-kanban-card-assignee"');

    // Card test hooks the orchestrator/M7 tests rely on.
    expect($src)
        ->toContain('data-column-key={column.key}')
        ->toContain('data-testid="nexus-kanban-card"');
});

it('REQ-M9-011: table-view preserves its TanStack interaction contract', function () {
    $src = tableViewSource();

    expect($src)
        ->toContain("from '@tanstack/react-table'")
        ->toContain('useReactTable')
        ->toContain('enableMultiSort: true')
        ->toContain('isMultiSortEvent:')
        ->toContain('shiftKey: event.shiftKey')
        ->toContain('globalFilterFn: fuzzyFilter')
        ->toContain('rankItem(')
        ->toContain('writeStateToUrl')
        ->toContain('readInitialStateFromUrl');

    // Still purely client-side.
    expect($src)
        ->not->toContain('router.visit')
        ->not->toContain('router.get')
        ->not->toContain('useHttp')
        ->not->toContain('fetch(');
});

it('REQ-M9-011: flowchart-view preserves the lazy mermaid integration', function () {
    $src = flowchartViewSource();

    expect($src)
        ->toContain("await import('mermaid')")
        ->toContain('mermaid.render(')
        ->toContain('mermaid_source')
        ->toContain('dangerouslySetInnerHTML');
});

it('REQ-M9-011: slide-deck-view preserves keyboard navigation contract', function () {
    $src = slideDeckViewSource();

    expect($src)
        ->toContain("addEventListener('keydown'")
        ->toContain("removeEventListener('keydown'")
        ->toContain('ArrowLeft')
        ->toContain('ArrowRight')
        ->toContain('preventDefault')
        ->toContain('data-slide-index={index}')
        ->toContain('data-slide-count={slides.length}');
});

it('REQ-M9-011: custom view JSX heading/button text uses Title Case', function () {
    foreach (customViewSources() as $file => $src) {
        $tagPattern = '<(h1|h2|h3|Button)[^>\n]*>\s*([a-z])';
        preg_match_all('/'.$tagPattern.'/m', $src, $matches, PREG_SET_ORDER);

        $offenders = array_map(
            fn ($m) => '<'.$m[1].'> starts with "'.$m[2].'"',
            $matches,
        );

        expect($offenders)->toBe(
            [],
            "{$file} contains lowercase JSX heading/button text — fix to Title Case: "
                .implode(', ', $offenders),
        );
    }
});

it('REQ-M9-011: snapshot route renders the table view end-to-end', function () {
    $owner = User::factory()->create();
    $wb = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snap = Snapshot::factory()->for($wb)->create();

    SnapshotVersioning::append(
        snapshot: $snap,
        viewType: 'table',
        dataPayload: [
            'columns' => [['key' => 'id'], ['key' => 'name']],
            'rows' => [['id' => 1, 'name' => 'Ada']],
        ],
    );

    actingAs($owner)
        ->withoutVite()
        ->get("/workbenches/{$wb->slug}/snapshots/{$snap->slug}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('snapshot')
            ->where('version.view_type', 'table')
            ->etc()
        );
});

it('REQ-M9-011: snapshot route renders the kanban view end-to-end', function () {
    $owner = User::factory()->create();
    $wb = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snap = Snapshot::factory()->for($wb)->create();

    SnapshotVersioning::append(
        snapshot: $snap,
        viewType: 'kanban',
        dataPayload: [
            'columns' => [['key' => 'todo', 'label' => 'To Do']],
            'cards' => [['column_key' => 'todo', 'title' => 'First card']],
        ],
    );

    actingAs($owner)
        ->withoutVite()
        ->get("/workbenches/{$wb->slug}/snapshots/{$snap->slug}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('snapshot')
            ->where('version.view_type', 'kanban')
            ->etc()
        );
});
