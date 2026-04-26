import { router } from '@inertiajs/react';
import { useCallback, useState } from 'react';

/**
 * REQ-M6-018: state for the "Agent pushed v{n}" banner.
 *
 * The polling loop (REQ-M6-016) brings down the latest `currentRevision` via
 * a partial reload. We compare it against the *initially rendered* revision
 * (captured once on mount via a lazy `useState` initialiser in the page
 * component) so the banner only appears when a fresh push has landed.
 * Clicking the banner navigates to the new revision via the same
 * query-string convention the version switcher uses (`?revision=N`), and
 * dismissals are tracked per-revision so a higher push re-arms the banner
 * automatically — no effect needed.
 */
export type UseRevisionBannerResult = {
    show: boolean;
    onView: () => void;
    onDismiss: () => void;
};

export function useRevisionBanner(
    renderedRevision: number,
    currentRevision: number,
    snapshotSlug: string,
    workbenchSlug: string,
): UseRevisionBannerResult {
    const [dismissedRevision, setDismissedRevision] = useState<number | null>(
        null,
    );

    // The banner shows when (a) a strictly higher revision has arrived and
    // (b) the user has not already dismissed *this exact* revision. If a
    // newer revision arrives later, `currentRevision > dismissedRevision`
    // becomes true again and the banner re-arms — no state reset required.
    const show =
        currentRevision > renderedRevision &&
        (dismissedRevision === null || currentRevision > dismissedRevision);

    const onView = useCallback(() => {
        // Same URL shape as the version switcher (REQ-M1-007).
        const path = `/workbenches/${workbenchSlug}/snapshots/${snapshotSlug}?revision=${currentRevision}`;
        router.visit(path);
    }, [currentRevision, snapshotSlug, workbenchSlug]);

    const onDismiss = useCallback(() => {
        setDismissedRevision(currentRevision);
    }, [currentRevision]);

    return { show, onView, onDismiss };
}
