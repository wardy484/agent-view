<?php

declare(strict_types=1);

use App\Enums\SnapshotVisibility;
use App\Models\Snapshot;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

function makeSnapshotForViewModes(): array
{
    $workbench = Workbench::factory()->create(['slug' => 'team-alpha']);
    $snapshot = Snapshot::factory()->for($workbench)->create(['slug' => 'engineers']);

    SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'id']], 'rows' => [['id' => 1]]],
    );

    return [$workbench, $snapshot];
}

it('REQ-M3-010: authenticated request without ?mode resolves to mode=app and isAuthenticated=true', function (): void {
    [$workbench, $snapshot] = makeSnapshotForViewModes();

    // REQ-M4-005: the authenticated route is now gated on ownership / share.
    // Act as the workbench owner so the policy passes and we can assert the
    // M3-010 chrome-mode resolution.
    $this->actingAs($workbench->owner)
        ->withoutVite()
        ->get(route('workbench.snapshot.show', [
            'workbench' => $workbench->slug,
            'snapshot' => $snapshot->slug,
        ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('snapshot')
            ->where('mode', 'app')
            ->where('isAuthenticated', true)
        );
});

it('REQ-M3-010: ?mode=preview forces mode=preview even when authenticated', function (): void {
    [$workbench, $snapshot] = makeSnapshotForViewModes();

    $this->actingAs($workbench->owner)
        ->withoutVite()
        ->get(route('workbench.snapshot.show', [
            'workbench' => $workbench->slug,
            'snapshot' => $snapshot->slug,
            'mode' => 'preview',
        ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('mode', 'preview')
            ->where('isAuthenticated', true)
        );
});

it('REQ-M3-010: guest public-link viewer receives preview chrome and read-only flags', function (): void {
    // REQ-M4-005 blocks guests from the authenticated route. The M3-010 guest
    // scenario is now the public-link path `/s/{token}`, which produces the
    // same bare/preview chrome.
    [$workbench, $snapshot] = makeSnapshotForViewModes();

    $snapshot->setVisibility(SnapshotVisibility::Link);
    $snapshot->refresh();

    $this->withoutVite()
        ->get(route('snapshot.public', ['token' => $snapshot->share_token]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('snapshot')
            ->where('is_public_link', true)
            ->where('is_owner', false)
        );
});

it('REQ-M3-012: snapshot page wires preview-mode chrome via the PreviewHomeButton component', function (): void {
    // The page wrapper renders <PreviewHomeButton> in preview mode and the
    // workbench header (with VersionSwitcher) in app mode. We assert the
    // source uses both pieces and gates them on the `mode` prop.
    $source = file_get_contents(resource_path('js/pages/snapshot.tsx'));

    expect($source)
        ->toContain("from '@/layouts/app-layout'")
        ->toContain("from '@/components/nexus/preview-home-button'")
        ->toContain("mode === 'preview'")
        ->toContain('showAppShell')
        ->toContain('PreviewHomeButton');

    $homeButton = file_get_contents(resource_path('js/components/nexus/preview-home-button.tsx'));

    expect($homeButton)
        ->toContain("from '@inertiajs/react'")
        // Auth-aware target: dashboard for users, marketing home for guests.
        ->toContain("'/dashboard'")
        ->toContain("'/'")
        // Positioned top-right so it doesn't overlap top-left chrome (e.g. the
        // table view's fuzzy-search input) and z-[60] so it floats above full-
        // bleed presentation views (slide deck uses z-50 on fixed inset-0).
        ->toContain('right-3')
        ->toContain('z-[60]');
});

it('REQ-M3-013: snapshot page exposes the in-app fullscreen toggle gated on mode=app', function (): void {
    $source = file_get_contents(resource_path('js/pages/snapshot.tsx'));

    expect($source)
        // A toggle button identified for tests + an ESC handler that exits.
        ->toContain('nexus-fullscreen-toggle')
        ->toContain('setIsFullscreen')
        ->toContain("event.key === 'Escape'")
        // The toggle and workbench header are only rendered when not in preview mode.
        ->toContain('showWorkbenchHeader')
        ->toContain('Maximize2')
        ->toContain('Minimize2');
});
