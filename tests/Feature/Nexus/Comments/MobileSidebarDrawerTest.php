<?php

declare(strict_types=1);

/**
 * REQ-M6-020: mobile-first follow-up to REQ-M6-014. Below Tailwind's `lg`
 * breakpoint the snapshot sidebar collapses into a right-anchored slide-in
 * `<Sheet>` triggered by a "Comments" button (with an open-count badge) in
 * the report column header. Above `lg` the sidebar renders inline on the
 * right of the report (the unchanged REQ-M6-014 layout).
 *
 * These are file-shape assertions in the same style as
 * REQ-M6-013 / REQ-M6-014: they pin the literal markers tests look for so
 * future refactors can't silently drop the responsive presentation.
 */
it('REQ-M6-020: spec document records the mobile drawer requirement', function (): void {
    $spec = file_get_contents(base_path('docs/nexus-spec.md'));

    expect($spec)
        ->toContain('**REQ-M6-020**')
        ->toContain('lg')
        ->toContain('Comments')
        ->toContain('SnapshotSidebar')
        ->toContain('slide-in');
});

it('REQ-M6-020: ResponsiveSidebar wraps SnapshotSidebar in a Sheet below lg and renders inline above lg', function (): void {
    $wrapper = file_get_contents(
        resource_path('js/components/nexus/responsive-sidebar.tsx'),
    );

    expect($wrapper)
        ->toContain("from '@/components/ui/sheet'")
        ->toContain('Sheet')
        ->toContain('SheetContent')
        ->toContain('SheetTrigger')
        ->toContain('lg:hidden')
        ->toContain('side="right"')
        ->toContain('data-testid="snapshot-sidebar-mobile-trigger"');

    $report = file_get_contents(
        resource_path('js/components/nexus/report-view.tsx'),
    );

    expect($report)
        ->toContain("from '@/components/nexus/responsive-sidebar'")
        ->toContain('<ResponsiveSidebar')
        // Desktop inline column is rendered with hidden lg:flex so mobile
        // never double-renders the sidebar below the report.
        ->toContain('hidden shrink-0 lg:flex')
        ->toContain('snapshot-sidebar-desktop-inline');
});

it('REQ-M6-020: snapshot page renders a mobile-only Comments trigger button with an open-count badge', function (): void {
    $wrapper = file_get_contents(
        resource_path('js/components/nexus/responsive-sidebar.tsx'),
    );

    // The trigger row carries `lg:hidden` so desktop never sees it.
    expect($wrapper)
        ->toContain('data-testid="snapshot-sidebar-mobile-trigger-row"')
        ->toContain('lg:hidden')
        ->toContain('data-testid="snapshot-sidebar-mobile-trigger"')
        ->toContain('data-testid="snapshot-sidebar-mobile-trigger-badge"')
        ->toContain('openCommentCount');

    // The host wires the open-count projection in:
    $report = file_get_contents(
        resource_path('js/components/nexus/report-view.tsx'),
    );

    expect($report)
        ->toContain('openCommentCount')
        ->toContain("comment.status === 'open'");
});

it('REQ-M6-020: sheet uses forceMount so sidebar state survives open/close', function (): void {
    $wrapper = file_get_contents(
        resource_path('js/components/nexus/responsive-sidebar.tsx'),
    );

    expect($wrapper)->toContain('forceMount');
});

it('REQ-M6-020: trigger badge hides when there are zero open comments', function (): void {
    $wrapper = file_get_contents(
        resource_path('js/components/nexus/responsive-sidebar.tsx'),
    );

    // Badge node is gated behind a positive-count check so a snapshot with
    // no open comments never renders the count chip — only the icon + label.
    expect($wrapper)
        ->toContain('openCommentCount > 0')
        ->toContain('data-testid="snapshot-sidebar-mobile-trigger-badge"');
});
