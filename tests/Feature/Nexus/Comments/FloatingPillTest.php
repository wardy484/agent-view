<?php

declare(strict_types=1);

/**
 * REQ-M6-023: floating selection pill.
 *
 * Supersedes REQ-M6-021 + REQ-M6-022. The always-visible bottom toolbar
 * is replaced by a compact floating pill anchored to the selection's
 * bounding rect — above on lg+, below on smaller screens (the OS
 * selection bubble on Android Chrome / iOS Safari sits above the
 * highlight, so BELOW keeps our pill out of its way).
 *
 * Like its M6 siblings, frontend assertions pin the contract via
 * file-shape checks until Pest 4 browser support is wired into this
 * project.
 */
it('REQ-M6-023: spec records the floating pill requirement', function (): void {
    $spec = (string) file_get_contents(base_path('docs/nexus-spec.md'));

    expect($spec)
        ->toContain('**REQ-M6-023**')
        ->toContain('comment-selection-pill.tsx')
        ->toContain('ABOVE the selection')
        ->toContain('BELOW the selection')
        ->toContain('OS-rendered selection bubble')
        ->toContain('auto-hides on scroll');
});

it('REQ-M6-023: REQ-M6-021 paragraph is annotated as superseded', function (): void {
    $spec = (string) file_get_contents(base_path('docs/nexus-spec.md'));

    // Locate the M6-021 paragraph and assert it carries the supersession
    // sentence — the sentence must live inside the 021 paragraph itself,
    // not anywhere else in the document.
    $marker = '**REQ-M6-021**';
    $start = strpos($spec, $marker);
    expect($start)->not->toBeFalse();

    $end = strpos($spec, "\n- **REQ-M6-022**", (int) $start);
    expect($end)->not->toBeFalse();

    $paragraph = substr($spec, (int) $start, ((int) $end) - ((int) $start));

    expect($paragraph)
        ->toContain('Superseded by REQ-M6-023')
        ->toContain('floating pill');
});

it('REQ-M6-023: REQ-M6-022 paragraph is annotated as superseded', function (): void {
    $spec = (string) file_get_contents(base_path('docs/nexus-spec.md'));

    $marker = '**REQ-M6-022**';
    $start = strpos($spec, $marker);
    expect($start)->not->toBeFalse();

    $end = strpos($spec, "\n- **REQ-M6-023**", (int) $start);
    expect($end)->not->toBeFalse();

    $paragraph = substr($spec, (int) $start, ((int) $end) - ((int) $start));

    expect($paragraph)
        ->toContain('Superseded by REQ-M6-023')
        ->toContain('floating pill');
});

it('REQ-M6-023: comment-selection-pill component file exists with three icon buttons', function (): void {
    $path = resource_path('js/components/nexus/comment-selection-pill.tsx');

    expect(file_exists($path))->toBeTrue();

    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('export function CommentSelectionPill')
        // Three icon-only buttons via lucide-react.
        ->toContain('MessageSquare')
        ->toContain('PenLine')
        ->toContain('Copy')
        // Test hooks for the three actions.
        ->toContain('comment-selection-pill-comment')
        ->toContain('comment-selection-pill-suggest')
        ->toContain('comment-selection-pill-copy')
        // The pill itself is the toolbar role.
        ->toContain('data-testid="comment-selection-pill"');
});

it('REQ-M6-023: pill positions above selection on lg+ and below on smaller screens', function (): void {
    $path = resource_path('js/components/nexus/comment-selection-pill.tsx');
    $source = (string) file_get_contents($path);

    expect($source)
        // Runtime breakpoint detection — the pill flips position based on
        // the lg breakpoint (1024px).
        ->toContain('matchMedia')
        ->toContain('1024')
        // Both branches of the position math are present: above (rect.top
        // minus height/gap) and below (rect.bottom plus gap).
        ->toContain('rect.top')
        ->toContain('rect.bottom')
        ->toContain('data-position');
});

it('REQ-M6-023: pill source clears selection on window scroll', function (): void {
    $path = resource_path('js/components/nexus/comment-selection-pill.tsx');
    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain("addEventListener('scroll'")
        ->toContain("removeEventListener('scroll'")
        // Scroll handler closes the pill (clears the selection upstream).
        ->toContain('onClose');
});

it('REQ-M6-023: pill source disables Comment and Suggest when selection crosses blocks', function (): void {
    $path = resource_path('js/components/nexus/comment-selection-pill.tsx');
    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('crossesBlocks')
        ->toContain('blockId === null')
        ->toContain('Selection must stay within one block.');
});

it('REQ-M6-023: pill click handlers call captureSelection at click time', function (): void {
    $path = resource_path('js/components/nexus/comment-selection-pill.tsx');
    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('captureSelection')
        ->toContain("from '@/lib/selection-helpers'")
        ->toContain('captureSelection(containerRef.current)');
});

it('REQ-M6-023: report-view no longer renders the deleted toolbar (no lg:hidden wrapper)', function (): void {
    $report = (string) file_get_contents(
        resource_path('js/components/nexus/report-view.tsx'),
    );

    expect($report)
        // The single pill replaces both prior breakpoint-wrapped renderers.
        ->toContain('CommentSelectionPill')
        ->not->toContain('CommentSelectionToolbar')
        ->not->toContain('comment-selection-toolbar')
        // Old breakpoint wrappers are gone — the pill renders once and
        // positions itself based on viewport at runtime.
        ->not->toContain('hidden lg:block')
        ->not->toContain('lg:hidden');

    // The deleted toolbar component file must really be gone.
    expect(file_exists(resource_path('js/components/nexus/comment-selection-toolbar.tsx')))
        ->toBeFalse();
});

it('REQ-M6-023: report-view no longer applies pb-20 padding hack', function (): void {
    $report = (string) file_get_contents(
        resource_path('js/components/nexus/report-view.tsx'),
    );

    expect($report)
        ->not->toContain('pb-20 lg:pb-0')
        ->not->toContain('pb-20');
});

it('REQ-M6-024: pill uses position: fixed and viewport-relative coordinates', function (): void {
    $path = resource_path('js/components/nexus/comment-selection-pill.tsx');
    $source = (string) file_get_contents($path);

    expect($source)
        // Pill must render with position: fixed so its coordinates are
        // viewport-relative — unaffected by the report container's
        // position: relative ancestor.
        ->toContain('fixed z-50')
        ->not->toContain('absolute z-50')
        // Viewport-relative coords come straight from getBoundingClientRect();
        // no document-relative offsets should be applied.
        ->not->toContain('window.scrollY')
        ->not->toContain('window.scrollX');

    $spec = (string) file_get_contents(base_path('docs/nexus-spec.md'));
    expect($spec)->toContain('**REQ-M6-024**');
});
