<?php

declare(strict_types=1);

use App\Models\User;

it('REQ-M11-011: renders the seeded ui-baseline-report snapshot with markdown blocks and a version history control', function (): void {
    $user = User::query()->where('email', 'wardy484@gmail.com')->firstOrFail();

    $this->actingAs($user);

    $page = visit('/workbenches/ui-baseline/snapshots/ui-baseline-report');

    // Seeded markdown block renders both heading and body text. The seeded
    // payload is "# UI baseline\n\nThis is the canonical baseline report
    // fixture." — the markdown renderer outputs both pieces of text.
    $page->assertSee('UI baseline')
        ->assertSee('canonical baseline report fixture')
        // The report view always mounts the snapshot sidebar (REQ-M6-014),
        // whose History tab is the version-history navigator for owner
        // viewers — the seed has a single revision, so the inline
        // VersionSwitcher returns null and History is the control that
        // surfaces the revision list.
        ->assertSee('History')
        ->assertNoJavaScriptErrors();
});
