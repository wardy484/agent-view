import { useCallback, useEffect, useState } from 'react';

const STORAGE_PREFIX = 'nexus.pinToLatest.';

const DEFAULT_PINNED_VIEW_TYPES = new Set(['kanban']);

function storageKey(snapshotId: number): string {
    return `${STORAGE_PREFIX}${snapshotId}`;
}

function readStored(snapshotId: number): boolean | null {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const raw = window.localStorage.getItem(storageKey(snapshotId));

        if (raw === '1') {
            return true;
        }

        if (raw === '0') {
            return false;
        }

        return null;
    } catch {
        return null;
    }
}

export type UsePinToLatestResult = {
    pinned: boolean;
    setPinned: (value: boolean) => void;
};

export function usePinToLatest(
    snapshotId: number,
    viewType: string,
): UsePinToLatestResult {
    const [pinned, setPinnedState] = useState<boolean>(() => {
        const stored = readStored(snapshotId);

        if (stored !== null) {
            return stored;
        }

        return DEFAULT_PINNED_VIEW_TYPES.has(viewType);
    });

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        try {
            window.localStorage.setItem(
                storageKey(snapshotId),
                pinned ? '1' : '0',
            );
        } catch {
            // Quota / private-mode failures are non-fatal — pinning still
            // works for the current session.
        }
    }, [snapshotId, pinned]);

    const setPinned = useCallback((value: boolean) => {
        setPinnedState(value);
    }, []);

    return { pinned, setPinned };
}
