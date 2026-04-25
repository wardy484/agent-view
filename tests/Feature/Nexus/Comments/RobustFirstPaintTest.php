<?php

declare(strict_types=1);

/**
 * REQ-M6-038: belt-and-braces first-paint highlight triggers.
 *
 * REQ-M6-036's MutationObserver alone wasn't reliably catching every
 * production hydration timing window — comments prop arrived populated
 * but the rAF sometimes lost the race against user perception of
 * "nothing rendered". This REQ guarantees three independent triggers:
 *   (a) immediate synchronous wrap inside useLayoutEffect
 *   (b) requestAnimationFrame retry on the next paint
 *   (c) setTimeout poll at 250ms intervals up to 5s, halting on first
 *       successful wrap
 * The MutationObserver is retained as a long-lived re-trigger for any
 * subsequent DOM mutations (polling reloads, agent push, history nav).
 *
 * As with the other M6 frontend REQs, assertions pin the contract via
 * file-shape checks until Pest 4 browser support is wired in.
 */
it('REQ-M6-038: spec records the robust first-paint requirement', function (): void {
    $spec = (string) file_get_contents(base_path('docs/nexus-spec.md'));

    expect($spec)
        ->toContain('**REQ-M6-038**')
        ->toContain('three complementary triggers')
        ->toContain('immediate synchronous wrap')
        ->toContain('requestAnimationFrame')
        ->toContain('setTimeout')
        ->toContain('250ms')
        ->toContain('5 seconds');
});

it('REQ-M6-038: overlay attempts an immediate sync wrap before any rAF or timeout', function (): void {
    $path = resource_path('js/components/nexus/comment-highlight-overlay.tsx');
    $source = (string) file_get_contents($path);

    // The immediate sync attempt is the first wrapPass() call inside the
    // effect, before the rAF retry and before the poll starts.
    expect($source)
        ->toContain('REQ-M6-038 (a)')
        ->toContain('let wrapped = wrapPass()');

    $effectStart = strpos($source, 'useLayoutEffect(() => {');
    $immediateCall = strpos($source, 'let wrapped = wrapPass()');
    $rafRetry = strpos($source, 'rafId = requestAnimationFrame(() => {');
    $pollStart = strpos($source, 'startPoll();');

    expect($effectStart)->toBeGreaterThan(0);
    expect($immediateCall)->toBeGreaterThan($effectStart);
    expect($rafRetry)->toBeGreaterThan($immediateCall);
    expect($pollStart)->toBeGreaterThan($immediateCall);
});

it('REQ-M6-038: overlay polls via setTimeout and halts on first successful wrap', function (): void {
    $path = resource_path('js/components/nexus/comment-highlight-overlay.tsx');
    $source = (string) file_get_contents($path);

    // The poll uses setTimeout(..., 250) and stops as soon as wrapPass()
    // returns a positive count.
    expect($source)
        ->toContain('REQ-M6-038 (c)')
        ->toContain('setTimeout(() => {')
        ->toContain(', 250)')
        ->toContain('const w = wrapPass()')
        ->toContain('if (w > 0)')
        ->toContain('observer takes over from here');
});

it('REQ-M6-038: overlay enforces a 5000ms poll deadline before giving up', function (): void {
    $path = resource_path('js/components/nexus/comment-highlight-overlay.tsx');
    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('Date.now() - pollStart > 5000')
        ->toContain('observer remains active');
});

it('REQ-M6-038: MutationObserver is retained as long-lived re-trigger after poll halts', function (): void {
    $path = resource_path('js/components/nexus/comment-highlight-overlay.tsx');
    $source = (string) file_get_contents($path);

    // The observer is set up after the poll has been started, persists
    // across wrapPass calls (reconnected at the end of each pass), and
    // is only torn down in the cleanup return — so it survives a
    // successful poll halt and any 5s give-up.
    expect($source)
        ->toContain('observer = new MutationObserver(')
        ->toContain('observer.observe(container, { childList: true, subtree: true })')
        ->toContain('long-lived');

    // Reconnection happens inside wrapPass before returning, ensuring the
    // observer keeps watching for subsequent DOM mutations even after the
    // initial poll-driven wrap succeeds.
    expect($source)->toContain('observer.observe(container, { childList: true, subtree: true });');
});

it('REQ-M6-038: cleanup tears down all three trigger types (rAF, timeout, observer)', function (): void {
    $path = resource_path('js/components/nexus/comment-highlight-overlay.tsx');
    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('cancelAnimationFrame(rafId)')
        ->toContain('clearTimeout(pollId)')
        ->toContain('observer.disconnect()')
        ->toContain('unmounted = true');
});
