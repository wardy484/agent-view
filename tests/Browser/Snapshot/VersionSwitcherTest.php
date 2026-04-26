<?php

declare(strict_types=1);

use App\Models\Snapshot;
use App\Models\User;
use App\Nexus\SnapshotVersioning;

it('REQ-M11-014: switches between revisions via the inline version switcher', function (): void {
    $user = User::query()->where('email', 'wardy484@gmail.com')->firstOrFail();

    $this->actingAs($user);

    $snapshot = Snapshot::query()->where('slug', 'ui-baseline-report')->firstOrFail();

    // Append a second revision with markedly distinct content so the
    // assertions below can tell the two revisions apart on the rendered page.
    SnapshotVersioning::append($snapshot, 'report', [
        'blocks' => [
            [
                'type' => 'markdown',
                'body' => "# v2 baseline heading\n\nrevision two body text",
            ],
        ],
    ]);

    $page = visit('/workbenches/ui-baseline/snapshots/ui-baseline-report');

    // Latest revision (r2) is rendered by default — v2 content is visible
    // and the v1 fixture body is no longer on the page.
    $page->assertSee('v2 baseline heading')
        ->assertSee('revision two body text')
        ->assertDontSee('canonical baseline report fixture')
        ->assertNoJavaScriptErrors();

    // The inline VersionSwitcher mounts when there are >= 2 revisions and
    // labels each revision link as `r{n}`. Clicking r1 navigates back to
    // the original revision via `?revision=1`.
    $page->click('@nexus-version-switcher')
        ->click('r1')
        ->assertSee('canonical baseline report fixture')
        ->assertDontSee('revision two body text')
        ->assertNoJavaScriptErrors();

    // Click back to r2 (the latest) — v2 content is restored.
    $page->click('@nexus-version-switcher')
        ->click('r2')
        ->assertSee('v2 baseline heading')
        ->assertSee('revision two body text')
        ->assertDontSee('canonical baseline report fixture')
        ->assertNoJavaScriptErrors();
});
