import { router } from '@inertiajs/react';
import { useEffect } from 'react';

/**
 * REQ-M6-016: poll the snapshot page for sidebar deltas every 8 seconds while
 * the tab is foregrounded. We use Inertia v3's partial-reload mechanism
 * (`router.reload({ only: [...] })`) so only the props that drive the sidebar
 * re-fetch — the renderer keeps its scroll position and component state.
 *
 * The interval is gated on `document.visibilityState === 'visible'` so a
 * background tab does not burn server cycles. When the tab regains focus the
 * loop fires immediately and resumes ticking.
 *
 * Historical views (REQ-M6-015) opt out entirely: the page is read-only and
 * is intentionally pinned to a non-current revision. Polling there would
 * undo what the user explicitly asked for.
 *
 * The hook is intentionally side-effect-only — REQ-M6-018 will render the
 * "v{n} pushed" banner on top of the props this loop refreshes.
 */
export function useSidebarPolling(
    snapshotId: number,
    currentRevision: number,
    commentsRevision: number,
    isHistoricalView: boolean,
): void {
    useEffect(() => {
        if (isHistoricalView) {
            return;
        }

        if (typeof document === 'undefined' || typeof window === 'undefined') {
            return;
        }

        let intervalId: ReturnType<typeof setInterval> | null = null;

        function tick() {
            if (document.visibilityState !== 'visible') {
                return;
            }

            router.reload({
                only: ['comments', 'versionHistory', 'snapshot', 'version'],
                preserveUrl: true,
            });
        }

        function start() {
            if (intervalId !== null) {
                return;
            }

            intervalId = setInterval(tick, 8000);
        }

        function stop() {
            if (intervalId === null) {
                return;
            }

            clearInterval(intervalId);
            intervalId = null;
        }

        function handleVisibilityChange() {
            if (document.visibilityState === 'visible') {
                start();
                // Fire one immediate poll on focus so the tab catches up
                // without waiting up to 8 seconds for the next tick.
                tick();
            } else {
                stop();
            }
        }

        if (document.visibilityState === 'visible') {
            start();
        }

        document.addEventListener('visibilitychange', handleVisibilityChange);

        return () => {
            stop();
            document.removeEventListener('visibilitychange', handleVisibilityChange);
        };
        // snapshotId / currentRevision / commentsRevision feed re-runs so
        // a background revision swap or a manual navigation re-arms the loop
        // against the freshly rendered props.
    }, [snapshotId, currentRevision, commentsRevision, isHistoricalView]);
}
