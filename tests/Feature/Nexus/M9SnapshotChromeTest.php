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
 * REQ-M9-009 — Refresh `pages/snapshot.tsx` page chrome (header, breadcrumb,
 * share dialog) and `components/nexus/version-switcher.tsx` against the new
 * tokens. The view dispatcher logic is untouched. Only chrome surfaces move
 * to the new design system.
 */
function snapshotChromeSource(): string
{
    return file_get_contents(resource_path('js/pages/snapshot.tsx'));
}

function versionSwitcherSource(): string
{
    return file_get_contents(resource_path('js/components/nexus/version-switcher.tsx'));
}

function shareDialogSource(): string
{
    return file_get_contents(resource_path('js/components/nexus/share-dialog.tsx'));
}

it('REQ-M9-009: snapshot chrome imports shadcn primitives and a Breadcrumb', function () {
    $src = snapshotChromeSource();

    expect($src)
        ->toContain("from '@/components/ui/button'")
        ->toContain("from '@/components/ui/breadcrumb'");

    // The chrome itself renders a breadcrumb so users always know where they
    // are in the workbench / snapshot hierarchy.
    expect($src)
        ->toContain('<Breadcrumb')
        ->toContain('<BreadcrumbList')
        ->toContain('<BreadcrumbItem');
});

it('REQ-M9-009: snapshot chrome uses the M9 typography scale on the page title', function () {
    $src = snapshotChromeSource();

    // M9 sets page titles at text-3xl font-semibold tracking-tight (REQ-M9-007).
    expect($src)->toContain('text-3xl font-semibold tracking-tight');
});

it('REQ-M9-009: snapshot chrome uses no arbitrary text/radius/shadow brackets', function () {
    $src = snapshotChromeSource();

    expect(preg_match('/text-\[\d+(\.\d+)?px\]/', $src))
        ->toBe(0, 'snapshot.tsx must use the M9 type tokens, not text-[NNpx] arbitrary values.');

    expect(preg_match('/rounded-\[[^\]]+\]/', $src))
        ->toBe(0, 'snapshot.tsx must use rounded-md/lg, not rounded-[…].');

    expect(preg_match('/shadow-\[[^\]]+\]/', $src))
        ->toBe(0, 'snapshot.tsx must not use arbitrary shadow brackets.');
});

it('REQ-M9-009: snapshot chrome drops nx-* legacy utility classes', function () {
    $src = snapshotChromeSource();

    expect($src)
        ->not->toContain('nx-card')
        ->not->toContain('nx-btn')
        ->not->toContain('nx-stage-header');
});

it('REQ-M9-009: snapshot chrome JSX heading/button text uses Title Case', function () {
    $src = snapshotChromeSource();

    $tagPattern = '<(h1|h2|h3|Button)[^>\n]*>\s*([a-z])';
    preg_match_all('/'.$tagPattern.'/m', $src, $matches, PREG_SET_ORDER);

    $offenders = array_map(
        fn ($m) => '<'.$m[1].'> starts with "'.$m[2].'"',
        $matches,
    );

    expect($offenders)->toBe(
        [],
        'snapshot.tsx contains lowercase JSX heading/button text — fix to Title Case: '
            .implode(', ', $offenders),
    );
});

it('REQ-M9-009: snapshot chrome preserves the view dispatcher logic intact', function () {
    $src = snapshotChromeSource();

    // The view dispatcher contract: every supported view_type still routes to
    // its dedicated component. This asserts we did not collapse or rewire it.
    expect($src)
        ->toContain("view_type === 'table'")
        ->toContain("view_type === 'kanban'")
        ->toContain("view_type === 'flowchart'")
        ->toContain("view_type === 'slide_deck'")
        ->toContain("view_type === 'report'")
        ->toContain('<TableView')
        ->toContain('<KanbanView')
        ->toContain('<FlowchartView')
        ->toContain('<SlideDeckView')
        ->toContain('<ReportView');
});

it('REQ-M9-009: snapshot chrome preserves the Echo / Reverb subscription wiring', function () {
    $src = snapshotChromeSource();

    // REQ-M7-002/003: Echo subscription must remain in place. We assert the
    // exact integration points so this REQ cannot accidentally regress them.
    expect($src)
        ->toContain('Echo')
        ->toContain('echo.private')
        ->toContain("'.SnapshotVersionAppended'")
        ->toContain('useSidebarPolling');
});

it('REQ-M9-009: version-switcher uses the shadcn DropdownMenu primitives', function () {
    $src = versionSwitcherSource();

    expect($src)
        ->toContain("from '@/components/ui/dropdown-menu'")
        ->toContain("from '@/components/ui/button'");

    expect($src)
        ->toContain('<DropdownMenu')
        ->toContain('<DropdownMenuTrigger')
        ->toContain('<DropdownMenuContent')
        ->toContain('<DropdownMenuItem');
});

it('REQ-M9-009: version-switcher uses no arbitrary brackets and no nx-* classes', function () {
    $src = versionSwitcherSource();

    expect(preg_match('/text-\[\d+(\.\d+)?px\]/', $src))->toBe(0);
    expect(preg_match('/rounded-\[[^\]]+\]/', $src))->toBe(0);
    expect(preg_match('/shadow-\[[^\]]+\]/', $src))->toBe(0);

    expect($src)
        ->not->toContain('nx-card')
        ->not->toContain('nx-btn');
});

it('REQ-M9-009: version-switcher Button text uses Title Case', function () {
    $src = versionSwitcherSource();

    $tagPattern = '<(h1|h2|h3|Button)[^>\n]*>\s*([a-z])';
    preg_match_all('/'.$tagPattern.'/m', $src, $matches, PREG_SET_ORDER);

    $offenders = array_map(
        fn ($m) => '<'.$m[1].'> starts with "'.$m[2].'"',
        $matches,
    );

    expect($offenders)->toBe(
        [],
        'version-switcher.tsx contains lowercase JSX heading/button text: '
            .implode(', ', $offenders),
    );
});

it('REQ-M9-009: version-switcher preserves its href contract (?revision=N)', function () {
    $src = versionSwitcherSource();

    // The router contract is part of the M1 spec; the visual rebuild must not
    // disturb how revisions are addressed.
    expect($src)
        ->toContain("'revision'")
        ->toContain('/workbenches/');
});

it('REQ-M9-009: share-dialog uses Title Case for headings and buttons', function () {
    $src = shareDialogSource();

    // DialogTitle counts as a heading in spirit; we also enforce Button text.
    $tagPattern = '<(h1|h2|h3|Button|DialogTitle)[^>\n]*>\s*([a-z])';
    preg_match_all('/'.$tagPattern.'/m', $src, $matches, PREG_SET_ORDER);

    $offenders = array_map(
        fn ($m) => '<'.$m[1].'> starts with "'.$m[2].'"',
        $matches,
    );

    expect($offenders)->toBe(
        [],
        'share-dialog.tsx contains lowercase JSX heading/button text: '
            .implode(', ', $offenders),
    );
});

it('REQ-M9-009: share-dialog uses no arbitrary brackets or nx-* classes', function () {
    $src = shareDialogSource();

    expect(preg_match('/text-\[\d+(\.\d+)?px\]/', $src))->toBe(0);
    expect(preg_match('/rounded-\[[^\]]+\]/', $src))->toBe(0);
    expect(preg_match('/shadow-\[[^\]]+\]/', $src))->toBe(0);

    expect($src)
        ->not->toContain('nx-card')
        ->not->toContain('nx-btn');
});

it('REQ-M9-009: snapshot route still renders with the refreshed chrome', function () {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snapshot = Snapshot::factory()->for($workbench)->create();
    SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'id']], 'rows' => [['id' => 1]]],
    );

    actingAs($owner)
        ->withoutVite()
        ->get("/workbenches/{$workbench->slug}/snapshots/{$snapshot->slug}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('snapshot')
            ->where('snapshot.slug', $snapshot->slug)
            ->where('version.view_type', 'table')
            ->etc()
        );
});
