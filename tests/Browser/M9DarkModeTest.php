<?php

declare(strict_types=1);

use App\Models\Snapshot;
use App\Models\User;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * REQ-M9-012 — Pest browser test that flips the theme between light and dark
 * on every refreshed surface and asserts no console errors.
 *
 * The app toggles `.dark` on <html> (see resources/js/hooks/use-appearance.tsx)
 * rather than `data-theme`; we exercise the same mechanism and additionally set
 * `data-theme` so the assertion covers both conventions.
 *
 * Note: this test depends on `pestphp/pest-plugin-browser` (Playwright). Where
 * a headless browser is unavailable, `php artisan test --filter REQ-M9-012`
 * still executes the matching Feature/Nexus/M9DarkModeTest.php which performs
 * a static-analysis pass over the same surfaces.
 */

/**
 * @return array<int, array{0: string, 1: string}>
 */
function m9MajorRoutes(): array
{
    return [
        ['/', 'welcome'],
        ['/login', 'login'],
        ['/register', 'register'],
        ['/dashboard', 'dashboard'],
        ['/settings/profile', 'settings/profile'],
        ['/settings/tokens', 'settings/tokens'],
    ];
}

it('REQ-M9-012: each major route renders without console errors in light and dark modes', function () {
    if (! function_exists('visit')) {
        $this->markTestSkipped('Pest browser plugin not installed in this environment.');
    }

    $owner = User::factory()->create();

    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id, 'slug' => 'm9-dark']);
    $snapshot = Snapshot::factory()->for($workbench)->create(['slug' => 'dark-mode-fixture']);
    SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'report',
        dataPayload: [
            'blocks' => [
                ['type' => 'markdown', 'body' => "# Heading\n\nA paragraph for the dark-mode walk-through."],
            ],
        ],
    );

    actingAs($owner);

    foreach (array_merge(m9MajorRoutes(), [['/workbench/m9-dark/snapshot/dark-mode-fixture', 'snapshot']]) as [$path, $label]) {
        foreach (['light', 'dark'] as $theme) {
            $page = visit($path);

            // Toggle both `.dark` (the app's mechanism) and `data-theme` (REQ wording).
            $page->script(sprintf(
                "document.documentElement.classList.toggle('dark', %s);"
                ."document.documentElement.dataset.theme = '%s';",
                $theme === 'dark' ? 'true' : 'false',
                $theme,
            ));

            $page->assertNoJavaScriptErrors();
        }
    }
});
