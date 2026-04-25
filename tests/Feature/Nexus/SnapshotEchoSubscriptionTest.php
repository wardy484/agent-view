<?php

declare(strict_types=1);

/**
 * REQ-M7-003 — `resources/js/pages/snapshot.tsx` subscribes to its snapshot's
 * private broadcast channel via Laravel Echo on mount and unsubscribes on
 * unmount. On a `SnapshotVersionAppended` event whose `revision` exceeds the
 * rendered revision, the page issues an Inertia partial reload of just
 * `snapshot` and `currentRevision` and re-renders behind a 200 ms fade.
 *
 * Browser tests are not configured in this project, so these assertions are
 * source-string checks against the React page and the Echo bootstrap module —
 * matching the pattern used in tests/Feature/Nexus/KanbanCardOptionalFieldsRenderingTest.php.
 */
it('REQ-M7-003: snapshot page imports the Echo bootstrap', function (): void {
    $source = file_get_contents(resource_path('js/app.tsx'));

    expect($source)->toContain("import './echo';");

    // The echo bootstrap itself must mount Echo on `window` so any page can
    // subscribe without re-importing — that's the contract snapshot.tsx relies on.
    $echo = file_get_contents(resource_path('js/echo.ts'));
    expect($echo)
        ->toContain("import Echo from 'laravel-echo'")
        ->toContain("import Pusher from 'pusher-js'")
        ->toContain('window.Pusher = Pusher')
        ->toContain('window.Echo = new Echo')
        ->toContain("broadcaster: 'reverb'")
        ->toContain('VITE_REVERB_APP_KEY');
});

it("REQ-M7-003: snapshot.tsx subscribes to Echo.private('snapshot.{id}')", function (): void {
    $source = file_get_contents(resource_path('js/pages/snapshot.tsx'));

    // The channel-name template must reference the snapshot id at runtime —
    // a literal `snapshot.${snapshotId}` (or `snapshot.${...}` derived from
    // props.snapshot.id) keeps the auth contract aligned with channels.php.
    expect($source)
        ->toContain('snapshot.${snapshotId}')
        ->toContain('echo.private(channelName)');
});

it('REQ-M7-003: snapshot.tsx listens for SnapshotVersionAppended events', function (): void {
    $source = file_get_contents(resource_path('js/pages/snapshot.tsx'));

    // The leading dot in `.SnapshotVersionAppended` is Echo's idiom for
    // "broadcastAs is the literal class name, no namespace prefix".
    expect($source)
        ->toContain(".listen('.SnapshotVersionAppended'")
        ->toContain('payload.revision > liveRevision')
        ->toContain('router.reload({')
        ->toContain("only: ['snapshot', 'currentRevision']");
});

it('REQ-M7-003: snapshot.tsx unsubscribes on unmount', function (): void {
    $source = file_get_contents(resource_path('js/pages/snapshot.tsx'));

    // The Echo subscription lives in a useEffect whose cleanup callback
    // calls `Echo.leave(...)` against the *private-* prefixed channel name
    // (Echo's documented contract for releasing private channels).
    expect($source)
        ->toContain('echo.leave(`private-${channelName}`)')
        ->toContain('return () => {');
});

it('REQ-M7-003: snapshot.tsx renders a 200 ms opacity fade for the swap', function (): void {
    $source = file_get_contents(resource_path('js/pages/snapshot.tsx'));

    // Tailwind primitive — `transition-opacity duration-200` is the swap
    // signal. We deliberately avoid a custom CSS file per the REQ.
    expect($source)
        ->toContain('transition-opacity duration-200')
        ->toContain('isFading');
});

it('REQ-M7-003: snapshot.tsx falls back silently when Echo is unavailable', function (): void {
    $source = file_get_contents(resource_path('js/pages/snapshot.tsx'));

    // A missing `window.Echo` (Reverb unreachable, bootstrap failed, or
    // disabled) must short-circuit cleanly. The synchronous try/catch around
    // the subscribe call also keeps a thrown ws-config error from breaking
    // the page. The fallback path is the M6-016 sidebar poll, invoked
    // unconditionally above the Echo effect, so its presence is the proof
    // that "Echo failure is silent".
    expect($source)
        ->toContain('if (!echo)')
        ->toContain('try {')
        ->toContain('} catch (error) {')
        ->toContain('useSidebarPolling');
});
