import { useEffect } from 'react';

import { cn } from '@/lib/utils';

type Props = {
    renderedRevision: number;
    latestRevision: number;
    onView: () => void;
    onDismiss: () => void;
    className?: string;
};

/**
 * REQ-M6-018: non-disruptive "Agent pushed v{n} · view changes" banner.
 *
 * Rendered above the snapshot's main column when polling (REQ-M6-016) brings
 * down a `currentRevision` higher than the rendered one. The banner does
 * NOT auto-swap the rendered content — it only invites the user to navigate
 * to the new revision. It pulses once on appearance, dismisses on click,
 * dismisses on tab blur (`visibilitychange`), and dismisses on ESC.
 */
export function NewRevisionBanner({
    renderedRevision,
    latestRevision,
    onView,
    onDismiss,
    className,
}: Props) {
    useEffect(() => {
        if (latestRevision <= renderedRevision) {
            return;
        }

        if (typeof document === 'undefined' || typeof window === 'undefined') {
            return;
        }

        function handleVisibilityChange() {
            // The banner exists to flag a fresh push. If the user blurs the
            // tab the prompt has lost its context — dismiss it so when they
            // return they see the banner only if the push still beats their
            // rendered revision (the next polling tick re-arms it).
            if (document.visibilityState !== 'visible') {
                onDismiss();
            }
        }

        function handleKeyDown(event: KeyboardEvent) {
            if (event.key === 'Escape') {
                onDismiss();
            }
        }

        document.addEventListener('visibilitychange', handleVisibilityChange);
        window.addEventListener('keydown', handleKeyDown);

        return () => {
            document.removeEventListener('visibilitychange', handleVisibilityChange);
            window.removeEventListener('keydown', handleKeyDown);
        };
    }, [latestRevision, renderedRevision, onDismiss]);

    if (latestRevision <= renderedRevision) {
        return null;
    }

    return (
        <div
            data-testid="nexus-new-revision-banner"
            className={cn(
                'sticky top-0 z-30 flex w-full items-center justify-center border-b border-primary/30 bg-primary/10 px-4 py-2 text-xs font-medium text-primary animate-pulse-once',
                className,
            )}
        >
            <button
                type="button"
                onClick={onView}
                className="inline-flex items-center gap-1 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                aria-label={`Agent pushed v${latestRevision}; view changes`}
            >
                <span>Agent pushed v{latestRevision}</span>
                <span aria-hidden>·</span>
                <span>view changes</span>
            </button>
        </div>
    );
}
