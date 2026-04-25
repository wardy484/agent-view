import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

import type {
    CommentAuthor,
    CommentReply,
    CommentSummary,
} from '@/components/nexus/snapshot-sidebar';

/**
 * REQ-M6-017: client-side optimistic mutation queue for the sidebar.
 *
 * Server-projected `comments` arrive as the source of truth via Inertia's
 * partial-reload polling loop (see {@see useSidebarPolling}). This hook
 * overlays a small queue of unreconciled mutations on top of that array so
 * the user sees their own writes immediately, with automatic rollback on
 * server rejection or after a hard 10s timeout.
 *
 * Mutation kinds:
 *  - `comment`:  a brand-new root comment authored locally.
 *  - `reply`:    a reply attached to an existing root.
 *  - `reaction`: a +/- delta to a comment's `reactions_summary` map.
 *  - `status`:   a flip of an existing comment's status.
 *
 * Every mutation carries a `clientId` (UUID v4 via `crypto.randomUUID()`).
 * The composer / reply box / reaction button / status dropdown fires the
 * mutation locally first, then POSTs to the server. On success we wait for
 * the polling tick to project the canonical row; if it lands within 10s we
 * drop the optimistic entry. On error we rollback immediately.
 *
 * The hook is intentionally side-effect free at render time. The pulse /
 * opacity styling for unreconciled rows lives on the consumer (the sidebar).
 */

export type OptimisticDraft = {
    blockId: string;
    body: string;
    kind: 'comment' | 'suggestion';
    proposedText?: string | null;
    anchor: {
        quote: string;
        prefix: string;
        suffix: string;
    };
    author: CommentAuthor;
};

type PendingComment = {
    kind: 'comment';
    clientId: string;
    blockId: string;
    body: string;
    commentKind: 'comment' | 'suggestion';
    proposedText: string | null;
    anchor: { quote: string; prefix: string; suffix: string };
    author: CommentAuthor;
};

type PendingReply = {
    kind: 'reply';
    clientId: string;
    parentCommentId: number;
    body: string;
    author: CommentAuthor;
};

type PendingReaction = {
    kind: 'reaction';
    clientId: string;
    commentId: number;
    emoji: string;
    delta: 1 | -1;
};

type PendingStatus = {
    kind: 'status';
    clientId: string;
    commentId: number;
    status: CommentSummary['status'];
};

type Pending = PendingComment | PendingReply | PendingReaction | PendingStatus;

const ROLLBACK_TIMEOUT_MS = 10_000;

export type UseOptimisticCommentsResult = {
    /** Server comments overlaid with unreconciled local mutations. */
    comments: CommentSummary[];
    /** Map of clientId -> pending mutation, for inspection / pulse styling. */
    pending: Map<string, Pending>;
    addOptimisticComment: (draft: OptimisticDraft) => string;
    addOptimisticReply: (parentId: number, body: string, author: CommentAuthor) => string;
    toggleOptimisticReaction: (commentId: number, emoji: string, isAdding: boolean) => string;
    flipOptimisticStatus: (commentId: number, status: CommentSummary['status']) => string;
    /** Drop a pending mutation (server rejection). */
    rollback: (clientId: string) => void;
    /**
     * Drop a pending mutation that has been observed in the server projection.
     * Consumers normally don't need to call this directly: the next polling
     * tick will project the canonical row and the hook auto-reconciles by
     * matching on (kind, body) for new comments / replies.
     */
    reconcile: (clientId: string) => void;
};

export function useOptimisticComments(
    serverComments: CommentSummary[],
): UseOptimisticCommentsResult {
    const [pending, setPending] = useState<Map<string, Pending>>(() => new Map());
    const timersRef = useRef<Map<string, ReturnType<typeof setTimeout>>>(new Map());

    // Auto-reconcile: when the server projection now contains a row that
    // matches an unreconciled mutation, drop the optimistic copy.
    //
    // We compute the set of reconcilable clientIds with useMemo (a pure
    // derivation from server + pending), then an effect drops them in one
    // setState call. Lint flags setState-in-effect because it can cascade,
    // but here the effect *only* fires when the memo value changes (which is
    // bounded: each clientId reconciles at most once), and the resulting
    // setState is itself idempotent for empty sets, so cascading is impossible.
    const reconcilable = useMemo(
        () => findReconcilableMutations(serverComments, pending),
        [serverComments, pending],
    );

    useEffect(() => {
        if (reconcilable.length === 0) {
            return;
        }

        // eslint-disable-next-line react-hooks/set-state-in-effect -- bounded reconciliation, see comment above
        setPending((current) => {
            let changed = false;
            const next = new Map(current);

            for (const clientId of reconcilable) {
                if (next.delete(clientId)) {
                    changed = true;
                }
            }

            return changed ? next : current;
        });
    }, [reconcilable]);

    // Cleanup any leftover rollback timers on unmount.
    useEffect(() => {
        const timers = timersRef.current;

        return () => {
            for (const timer of timers.values()) {
                clearTimeout(timer);
            }

            timers.clear();
        };
    }, []);

    const armTimeout = useCallback((clientId: string) => {
        const existing = timersRef.current.get(clientId);

        if (existing) {
            clearTimeout(existing);
        }

        const timer = setTimeout(() => {
            setPending((current) => {
                if (!current.has(clientId)) {
                    return current;
                }

                const next = new Map(current);
                next.delete(clientId);

                return next;
            });
            timersRef.current.delete(clientId);
        }, ROLLBACK_TIMEOUT_MS);

        timersRef.current.set(clientId, timer);
    }, []);

    const rollback = useCallback((clientId: string) => {
        setPending((current) => {
            if (!current.has(clientId)) {
                return current;
            }

            const next = new Map(current);
            next.delete(clientId);

            return next;
        });

        const timer = timersRef.current.get(clientId);

        if (timer) {
            clearTimeout(timer);
            timersRef.current.delete(clientId);
        }
    }, []);

    const reconcile = rollback;

    const addOptimisticComment = useCallback(
        (draft: OptimisticDraft): string => {
            const clientId = generateClientId();

            setPending((current) => {
                const next = new Map(current);
                next.set(clientId, {
                    kind: 'comment',
                    clientId,
                    blockId: draft.blockId,
                    body: draft.body,
                    commentKind: draft.kind,
                    proposedText: draft.proposedText ?? null,
                    anchor: draft.anchor,
                    author: draft.author,
                });

                return next;
            });

            armTimeout(clientId);

            return clientId;
        },
        [armTimeout],
    );

    const addOptimisticReply = useCallback(
        (parentId: number, body: string, author: CommentAuthor): string => {
            const clientId = generateClientId();

            setPending((current) => {
                const next = new Map(current);
                next.set(clientId, {
                    kind: 'reply',
                    clientId,
                    parentCommentId: parentId,
                    body,
                    author,
                });

                return next;
            });

            armTimeout(clientId);

            return clientId;
        },
        [armTimeout],
    );

    const toggleOptimisticReaction = useCallback(
        (commentId: number, emoji: string, isAdding: boolean): string => {
            const clientId = generateClientId();

            setPending((current) => {
                const next = new Map(current);
                next.set(clientId, {
                    kind: 'reaction',
                    clientId,
                    commentId,
                    emoji,
                    delta: isAdding ? 1 : -1,
                });

                return next;
            });

            armTimeout(clientId);

            return clientId;
        },
        [armTimeout],
    );

    const flipOptimisticStatus = useCallback(
        (commentId: number, status: CommentSummary['status']): string => {
            const clientId = generateClientId();

            setPending((current) => {
                const next = new Map(current);
                next.set(clientId, {
                    kind: 'status',
                    clientId,
                    commentId,
                    status,
                });

                return next;
            });

            armTimeout(clientId);

            return clientId;
        },
        [armTimeout],
    );

    const comments = useMemo(
        () => projectComments(serverComments, pending),
        [serverComments, pending],
    );

    return {
        comments,
        pending,
        addOptimisticComment,
        addOptimisticReply,
        toggleOptimisticReaction,
        flipOptimisticStatus,
        rollback,
        reconcile,
    };
}

function findReconcilableMutations(
    serverComments: CommentSummary[],
    pending: Map<string, Pending>,
): string[] {
    if (pending.size === 0) {
        return [];
    }

    const ready: string[] = [];

    for (const [clientId, mutation] of pending) {
        if (mutation.kind === 'comment') {
            const match = serverComments.find(
                (c) =>
                    c.block_id === mutation.blockId &&
                    c.body === mutation.body &&
                    c.kind === mutation.commentKind,
            );

            if (match) {
                ready.push(clientId);
            }
        } else if (mutation.kind === 'reply') {
            const parent = serverComments.find((c) => c.id === mutation.parentCommentId);

            if (parent && parent.thread.some((r) => r.body === mutation.body)) {
                ready.push(clientId);
            }
        } else if (mutation.kind === 'reaction') {
            // Reactions reconcile silently once the server projection has
            // any version of the row — the canonical count overrides our delta.
            if (serverComments.some((c) => c.id === mutation.commentId)) {
                ready.push(clientId);
            }
        } else if (mutation.kind === 'status') {
            const target = serverComments.find((c) => c.id === mutation.commentId);

            if (target && target.status === mutation.status) {
                ready.push(clientId);
            }
        }
    }

    return ready;
}

function projectComments(
    serverComments: CommentSummary[],
    pending: Map<string, Pending>,
): CommentSummary[] {
    if (pending.size === 0) {
        return serverComments;
    }

    // Start with a shallow copy so we can mutate-by-replace without
    // touching the underlying server array.
    let next: CommentSummary[] = serverComments.map((c) => ({
        ...c,
        thread: [...c.thread],
        reactions_summary: { ...c.reactions_summary },
    }));

    for (const mutation of pending.values()) {
        if (mutation.kind === 'comment') {
            next.push({
                id: temporaryRowId(mutation.clientId),
                block_id: mutation.blockId,
                status: 'open',
                anchor: {
                    quote: mutation.anchor.quote,
                    prefix: mutation.anchor.prefix,
                    suffix: mutation.anchor.suffix,
                    resolved_in_current_version: true,
                },
                body: mutation.body,
                kind: mutation.commentKind,
                proposed_text: mutation.proposedText ?? undefined,
                author: mutation.author,
                created_at: null,
                created_on_revision: null,
                addressed_on_revision: null,
                thread: [],
                reactions_summary: {},
                // Cast-safe: we add this for the renderer to detect optimistic rows.
                ...(({ __clientId: mutation.clientId } as unknown) as Record<string, never>),
            });

            continue;
        }

        if (mutation.kind === 'reply') {
            next = next.map((c) => {
                if (c.id !== mutation.parentCommentId) {
                    return c;
                }

                const reply: CommentReply = {
                    id: temporaryRowId(mutation.clientId),
                    body: mutation.body,
                    author: mutation.author,
                    created_at: null,
                };

                return {
                    ...c,
                    thread: [...c.thread, reply],
                };
            });

            continue;
        }

        if (mutation.kind === 'reaction') {
            next = next.map((c) => {
                if (c.id !== mutation.commentId) {
                    return c;
                }

                const summary = { ...c.reactions_summary };
                const current = summary[mutation.emoji] ?? 0;
                const updated = current + mutation.delta;

                if (updated <= 0) {
                    delete summary[mutation.emoji];
                } else {
                    summary[mutation.emoji] = updated;
                }

                return { ...c, reactions_summary: summary };
            });

            continue;
        }

        if (mutation.kind === 'status') {
            next = next.map((c) =>
                c.id === mutation.commentId ? { ...c, status: mutation.status } : c,
            );
        }
    }

    return next;
}

export function temporaryRowIdFromClientId(clientId: string): number {
    return temporaryRowId(clientId);
}

function temporaryRowId(clientId: string): number {
    // Stable negative integer id derived from the clientId, used as the React
    // list `key` for unreconciled rows. Negative so it cannot collide with a
    // real server-issued positive id.
    let hash = 0;

    for (let i = 0; i < clientId.length; i += 1) {
        hash = (hash * 31 + clientId.charCodeAt(i)) | 0;
    }

    return -Math.abs(hash || 1);
}

function generateClientId(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    // Fallback for non-secure-context test envs. Not RFC4122 — that's fine,
    // the value only needs to be unique within this tab.
    return `cid-${Math.random().toString(36).slice(2)}-${Date.now().toString(36)}`;
}
