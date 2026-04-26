<?php

declare(strict_types=1);

use App\Models\Snapshot;
use App\Models\User;
use App\Nexus\SnapshotVersioning;

it('REQ-M11-013: renders a slide deck snapshot and navigates between slides', function (): void {
    $user = User::query()->where('email', 'wardy484@gmail.com')->firstOrFail();

    $this->actingAs($user);

    $snapshot = Snapshot::query()
        ->where('slug', 'ui-baseline-slide_deck')
        ->firstOrFail();

    // The seeded fixture has only one slide. Append a fresh revision with two
    // slides so we can drive next/previous navigation. SnapshotVersioning::append()
    // is the sole authorised writer; revision is monotonic so the snapshot will
    // surface this newer payload as `current_version`.
    SnapshotVersioning::append(
        $snapshot,
        'slide_deck',
        [
            'slides' => [
                [
                    'title' => 'Slide one heading',
                    'body_md' => '- first slide bullet',
                ],
                [
                    'title' => 'Slide two heading',
                    'body_md' => '- second slide bullet',
                ],
            ],
        ],
    );

    $page = visit('/workbenches/ui-baseline/snapshots/ui-baseline-slide_deck');

    // First slide is rendered initially.
    $page->assertSee('Slide one heading')
        ->assertDontSee('Slide two heading')
        ->assertNoJavaScriptErrors();

    // Click the next-slide control. In embedded mode the snapshot page renders
    // text-labelled "Previous" / "Next" buttons.
    $page->click('Next')
        ->assertNoJavaScriptErrors()
        ->assertSee('Slide two heading')
        ->assertDontSee('Slide one heading');

    // Click previous to return to the first slide.
    $page->click('Previous')
        ->assertNoJavaScriptErrors()
        ->assertSee('Slide one heading')
        ->assertDontSee('Slide two heading');
});
