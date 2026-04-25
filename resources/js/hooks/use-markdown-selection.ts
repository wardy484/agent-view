import { useEffect, useState } from 'react';

import { captureSelection, findBlockAncestor, offsetWithinBlock } from '@/lib/selection-helpers';
import type { SelectionInfo } from '@/lib/selection-helpers';

/**
 * REQ-M6-013 / REQ-M6-021: tracks the user's text selection inside a
 * container that hosts markdown blocks. Each markdown block in the report
 * renderer is wrapped in an element carrying `data-comment-block-id="<uuid>"`;
 * the helpers walk up from the selection's start/end nodes to find that
 * ancestor.
 *
 * REQ-M6-021: in addition to `mouseup` and `keyup` (desktop), the hook
 * subscribes to `selectionchange` on `document` and `pointerup` on the
 * container so that touch selections on iOS/Android register reliably.
 * `selectionchange` is scoped: we only recompute when the current
 * selection's anchor node lives inside the container ref.
 *
 * REQ-M6-022: this hook now only powers reactive enable/disable feedback
 * for the toolbar's visual state. The actual click handlers in
 * `comment-selection-toolbar.tsx` re-read the live selection via
 * `captureSelection` at tap time — eliminating the touch timing race.
 * The 50ms `selectionchange` debounce has been replaced with a single
 * `requestAnimationFrame` coalesce (≤1 update per frame) so reactive
 * feedback stays responsive without thrashing React on iOS's many-per-
 * touch event stream.
 *
 * `findBlockAncestor` / `offsetWithinBlock` live in
 * `@/lib/selection-helpers` — pure refactor, behaviour unchanged.
 *
 * Caveat: `startHint` / `endHint` are character offsets computed by walking
 * the rendered DOM's text content within the block. The block's source
 * markdown body and its rendered text are equivalent for plain prose, but
 * formatting (bold, italics, code, links) introduces drift of a few chars.
 * The server-side `AnchorResolver` (REQ-M6-004) re-resolves via the
 * `quote` + `prefix` + `suffix` triple, treating the hints as a starting
 * point only.
 */

// Re-export for any module importing the type from this hook (M6-013 /
// M6-021 callers). Behaviour-preserving alias of the shared helper type.
export type { SelectionInfo };

// Re-export internal helpers so existing callers and tests can keep the
// previous import path while the implementations live in the shared module.
// `findBlockAncestor` and `offsetWithinBlock` are purely informational here.
export { findBlockAncestor, offsetWithinBlock };

export function useMarkdownSelection(
    containerRef: React.RefObject<HTMLElement | null>,
): SelectionInfo | null {
    const [info, setInfo] = useState<SelectionInfo | null>(null);

    useEffect(() => {
        const compute = () => {
            const container = containerRef.current;

            if (!container) {
                return;
            }

            // Reuse the same captureSelection helper the toolbar uses at
            // click time; this keeps the hook's reactive state and the
            // click-time read in lock-step.
            const next = captureSelection(container);

            setInfo(next);
        };

        // REQ-M6-022: replace the prior 50ms setTimeout debounce with a
        // requestAnimationFrame coalesce. iOS fires `selectionchange`
        // many times per touch drag; one paint-aligned recompute per
        // frame is enough for visual feedback and avoids React thrash.
        let rafHandle: number | null = null;

        const schedule = () => {
            if (rafHandle !== null) {
                return;
            }

            rafHandle = requestAnimationFrame(() => {
                rafHandle = null;
                compute();
            });
        };

        const onSelectionChange = () => {
            const container = containerRef.current;

            if (!container) {
                return;
            }

            const sel = window.getSelection();

            if (!sel || sel.rangeCount === 0) {
                return;
            }

            const anchorNode = sel.anchorNode;

            // Scope to the report container so an unrelated selection
            // elsewhere on the page does not clobber our state.
            if (!anchorNode || !container.contains(anchorNode)) {
                return;
            }

            schedule();
        };

        // REQ-M6-021: `pointerup` on the container fires reliably at the
        // end of a touch drag on iOS/Android, where `mouseup` is unreliable.
        const container = containerRef.current;

        document.addEventListener('selectionchange', onSelectionChange);
        document.addEventListener('mouseup', compute);
        document.addEventListener('keyup', compute);

        if (container) {
            container.addEventListener('pointerup', compute);
        }

        return () => {
            if (rafHandle !== null) {
                cancelAnimationFrame(rafHandle);
            }

            document.removeEventListener('selectionchange', onSelectionChange);
            document.removeEventListener('mouseup', compute);
            document.removeEventListener('keyup', compute);

            if (container) {
                container.removeEventListener('pointerup', compute);
            }
        };
    }, [containerRef]);

    return info;
}
