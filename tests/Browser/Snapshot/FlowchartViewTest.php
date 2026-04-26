<?php

declare(strict_types=1);

use App\Models\User;

it('REQ-M11-012: renders the seeded ui-baseline-flowchart snapshot with a mermaid <svg>', function (): void {
    $user = User::query()->where('email', 'wardy484@gmail.com')->firstOrFail();

    $this->actingAs($user);

    $page = visit('/workbenches/ui-baseline/snapshots/ui-baseline-flowchart');

    // Mermaid renders client-side; poll briefly for the rendered SVG to settle.
    // The flowchart-view component sets data-mermaid-rendered="true" once mermaid
    // has produced its <svg> output.
    $rendered = false;
    for ($i = 0; $i < 40; $i++) {
        $count = (int) $page->script(
            "document.querySelectorAll('[data-testid=\"nexus-flowchart-view\"] svg').length"
        );

        if ($count > 0) {
            $rendered = true;
            break;
        }

        usleep(250_000);
    }

    expect($rendered)->toBeTrue();

    // Final assertions: rendered SVG present, no JavaScript errors.
    // Mermaid is permitted to emit console warnings (level "warn" only); errors fail the test.
    $svgCount = (int) $page->script(
        "document.querySelectorAll('svg').length"
    );

    expect($svgCount)->toBeGreaterThan(0);

    $page->assertNoJavaScriptErrors();
});
