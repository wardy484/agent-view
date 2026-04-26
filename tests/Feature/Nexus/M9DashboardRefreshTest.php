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
 * REQ-M9-007 — Refresh `pages/dashboard.tsx` as the canonical reference page:
 * replace custom UI fragments with shadcn primitives, apply the new tokens,
 * fix casing. Subsequent page refresh REQs follow this pattern.
 */
function dashboardSource(): string
{
    return file_get_contents(resource_path('js/pages/dashboard.tsx'));
}

it('REQ-M9-007: dashboard imports and uses shadcn Card primitives', function () {
    $src = dashboardSource();

    // Imports come from the local shadcn ui/* path, not custom nx-card classes.
    expect($src)
        ->toContain("from '@/components/ui/card'")
        ->toContain("from '@/components/ui/button'")
        ->toContain("from '@/components/ui/badge'");

    // Primitives are actually rendered, not merely imported.
    expect($src)
        ->toContain('<Card')
        ->toContain('<CardHeader')
        ->toContain('<CardTitle')
        ->toContain('<CardContent')
        ->toContain('<Button')
        ->toContain('<Badge');
});

it('REQ-M9-007: dashboard drops the legacy nx-* utility classes', function () {
    $src = dashboardSource();

    expect($src)
        ->not->toContain('nx-card')
        ->not->toContain('nx-btn')
        ->not->toContain('nx-stage-header')
        ->not->toContain('nx-stage-title')
        ->not->toContain('nx-stage-body')
        ->not->toContain('nx-view-chip');
});

it('REQ-M9-007: dashboard uses token-based text sizes, not arbitrary pixel values', function () {
    $src = dashboardSource();

    // No arbitrary text-[NNpx] strings — those bypass the M9 type tokens.
    expect(preg_match('/text-\[\d+(\.\d+)?px\]/', $src))
        ->toBe(0, 'Dashboard must use text-xs/sm/base/lg/2xl/3xl tokens, not text-[NNpx] arbitrary values.');

    // No arbitrary rounded-[…] either — radius comes from the new token scale.
    expect(preg_match('/rounded-\[[^\]]+\]/', $src))
        ->toBe(0, 'Dashboard must use rounded-md/lg, not rounded-[…] arbitrary values.');
});

it('REQ-M9-007: dashboard headings, buttons, and nav text use Title Case (lint clean)', function () {
    $src = dashboardSource();

    // Tags ESLint's nexus-local/no-lowercase-titles guards.
    $tagPattern = '<(h1|h2|h3|Button)[^>]*>\s*([a-z])';
    preg_match_all('/'.$tagPattern.'/m', $src, $matches, PREG_SET_ORDER);

    $offenders = array_map(
        fn ($m) => '<'.$m[1].'> starts with "'.$m[2].'"',
        $matches,
    );

    expect($offenders)->toBe(
        [],
        'dashboard.tsx contains lowercase JSX heading/button text — fix to Title Case: '
            .implode(', ', $offenders),
    );
});

it('REQ-M9-007: dashboard preserves the M8 data contract for the empty state', function () {
    $newcomer = User::factory()->create();

    actingAs($newcomer)
        ->withoutVite()
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('dashboard')
            ->has('workbenches', 0)
            ->has('recentSnapshots', 0)
        );
});

it('REQ-M9-007: dashboard preserves the M8 data contract when workbenches exist', function () {
    $owner = User::factory()->create();

    $wb = Workbench::factory()->create(['owner_user_id' => $owner->id]);
    $snap = Snapshot::factory()->for($wb)->create();
    SnapshotVersioning::append(
        snapshot: $snap,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'id']], 'rows' => [['id' => 1]]],
    );

    actingAs($owner)
        ->withoutVite()
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('dashboard')
            ->has('recentSnapshots', 1)
            ->has('workbenches', 1)
            ->where('workbenches.0.snapshot_count', 1)
            ->etc()
        );
});
