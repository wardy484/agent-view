import { router } from '@inertiajs/react';
import { Bot, ChevronDown, ChevronRight, History, Info, MessageCircle, Smile, User as UserIcon, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';

import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { temporaryRowIdFromClientId, useOptimisticComments } from '@/hooks/use-optimistic-comments';
import { cn } from '@/lib/utils';

/**
 * REQ-M6-014: snapshot page right-hand sidebar. Two tabs — Comments
 * (default) and History.
 *
 * Comments tab groups roots by `block_id` in document order (taken from the
 * report's blocks[]) and renders each thread inline. Clicking a comment
 * scrolls into and briefly highlights the resolved anchor range, or shows a
 * "stale" toast when the anchor cannot be located in the current revision.
 *
 * History tab is a stub for REQ-M6-014 — it lists the revisions backed by
 * the `versionHistory` Inertia prop. The full History UI lands in M6-015.
 *
 * Composer wiring: when the report-view's CommentSelectionMenu fires
 * `onComment` / `onSuggest`, the parent passes a `composerSelection` so the
 * sidebar surfaces an inline composer at the top of the Comments tab. Submit
 * POSTs to `snapshots.comments.store` (no optimistic UI yet — that's
 * REQ-M6-017).
 */

export type CommentAuthor = {
    display_name: string;
    kind: 'user' | 'agent';
};

export type CommentReply = {
    id: number;
    body: string;
    author: CommentAuthor;
    created_at: string | null;
};

export type CommentAnchor = {
    quote: string;
    prefix: string;
    suffix: string;
    resolved_in_current_version: boolean;
};

export type CommentSummary = {
    id: number;
    block_id: string;
    status: 'open' | 'resolved' | 'stale' | 'wontfix';
    anchor: CommentAnchor;
    body: string;
    kind: 'comment' | 'suggestion';
    proposed_text?: string;
    author: CommentAuthor;
    created_at: string | null;
    created_on_revision: number | null;
    addressed_on_revision: number | null;
    thread: CommentReply[];
    reactions_summary: Record<string, number>;
};

export type AddressedCommentRow = {
    id: number;
    body_preview: string;
    author_kind: 'user' | 'agent';
    status: 'open' | 'resolved' | 'stale' | 'wontfix';
};

export type VersionHistoryEntry = {
    id: number;
    revision: number;
    view_type: string;
    author_kind: 'user' | 'agent';
    summary: string | null;
    is_current: boolean;
    /** REQ-M6-014 back-compat: id-only list. */
    addressed_comment_ids: number[];
    /** REQ-M6-015: full per-comment rows for the History tab expand-row UI. */
    addressed_comments: AddressedCommentRow[];
    created_at: string | null;
};

export type ComposerSelection = {
    blockId: string;
    quote: string;
    prefix: string;
    suffix: string;
    startHint: number;
    endHint: number;
    kind: 'comment' | 'suggestion';
};

type Props = {
    snapshotId: number;
    comments: CommentSummary[];
    versionHistory: VersionHistoryEntry[];
    blockOrder: string[];
    composerSelection: ComposerSelection | null;
    onComposerClose: () => void;
    /**
     * REQ-M6-015: when true the page is rendering a historical revision, so
     * the comment composer is hidden, the floating selection menu's write
     * actions are disabled, and the sidebar surfaces a read-only banner.
     */
    isHistoricalView?: boolean;
    /** REQ-M6-015: workbench + snapshot slug used to build version-switcher
     * URLs from the History tab's "View this version" affordance. */
    workbenchSlug?: string;
    snapshotSlug?: string;
    /** REQ-M6-015: revision currently being rendered (used to mark the row
     * the viewer is on, distinct from `is_current` which marks the latest). */
    activeRevision?: number;
};

type Tab = 'comments' | 'history';

export function SnapshotSidebar({
    snapshotId,
    comments,
    versionHistory,
    blockOrder,
    composerSelection,
    onComposerClose,
    isHistoricalView = false,
    workbenchSlug,
    snapshotSlug,
    activeRevision,
}: Props) {
    const [activeTab, setActiveTab] = useState<Tab>('comments');

    // REQ-M6-017: optimistic-UI overlay. The hook returns `comments` =
    // serverComments + unreconciled local mutations, plus a set of helpers
    // we hand to CommentCard / CommentComposer for create/reply/react/flip
    // flows. Each helper returns a `clientId` we pass to `router.post`'s
    // onError so a server rejection rolls the optimistic row back.
    const {
        comments: optimisticComments,
        pending,
        addOptimisticComment,
        addOptimisticReply,
        toggleOptimisticReaction,
        flipOptimisticStatus,
        rollback,
    } = useOptimisticComments(comments);

    const grouped = useMemo(
        () => groupCommentsByBlock(optimisticComments, blockOrder),
        [optimisticComments, blockOrder],
    );

    const pendingClientIds = useMemo(() => {
        const map = new Map<number, string>();

        for (const mutation of pending.values()) {
            if (mutation.kind === 'comment') {
                // Negative-id rows carry the optimistic clientId.
                // The CommentCard reads `data-optimistic` to apply the pulse.
                map.set(temporaryRowIdFromClientId(mutation.clientId), mutation.clientId);
            }
        }

        return map;
    }, [pending]);

    return (
        <aside
            data-testid="snapshot-sidebar"
            className={cn(
                'flex w-full shrink-0 flex-col border-border bg-background lg:w-[360px] lg:border-l',
            )}
        >
            <div role="tablist" className="flex border-b border-border">
                <SidebarTab
                    label="Comments"
                    value="comments"
                    activeTab={activeTab}
                    onSelect={setActiveTab}
                    count={comments.length}
                />
                <SidebarTab
                    label="History"
                    value="history"
                    activeTab={activeTab}
                    onSelect={setActiveTab}
                    count={versionHistory.length}
                />
            </div>

            {activeTab === 'comments' ? (
                <CommentsTab
                    snapshotId={snapshotId}
                    grouped={grouped}
                    composerSelection={composerSelection}
                    onComposerClose={onComposerClose}
                    isHistoricalView={isHistoricalView}
                    activeRevision={activeRevision}
                    pendingClientIds={pendingClientIds}
                    addOptimisticComment={addOptimisticComment}
                    addOptimisticReply={addOptimisticReply}
                    toggleOptimisticReaction={toggleOptimisticReaction}
                    flipOptimisticStatus={flipOptimisticStatus}
                    rollback={rollback}
                />
            ) : (
                <HistoryTab
                    versionHistory={versionHistory}
                    workbenchSlug={workbenchSlug}
                    snapshotSlug={snapshotSlug}
                    activeRevision={activeRevision}
                    onJumpToComment={(commentId, blockId, quote) => {
                        setActiveTab('comments');
                        // Defer to next paint so the Comments tab body is mounted
                        // before we try to scroll to / highlight the anchor.
                        window.setTimeout(() => {
                            scrollToAndHighlightAnchor(blockId, quote);
                            const card = document.querySelector<HTMLElement>(
                                `[data-comment-id="${commentId}"]`,
                            );

                            if (card) {
                                card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            }
                        }, 0);
                    }}
                />
            )}
        </aside>
    );
}

function SidebarTab({
    label,
    value,
    activeTab,
    onSelect,
    count,
}: {
    label: string;
    value: Tab;
    activeTab: Tab;
    onSelect: (tab: Tab) => void;
    count: number;
}) {
    const active = activeTab === value;

    return (
        <button
            type="button"
            role="tab"
            aria-selected={active}
            data-testid={`snapshot-sidebar-tab-${value}`}
            onClick={() => onSelect(value)}
            className={cn(
                'flex flex-1 items-center justify-center gap-2 px-3 py-2 text-sm font-medium transition-colors',
                active
                    ? 'border-b-2 border-primary text-foreground'
                    : 'border-b-2 border-transparent text-muted-foreground hover:text-foreground',
            )}
        >
            <span>{label}</span>
            <span
                className={cn(
                    'inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full px-1.5 text-xs font-medium',
                    active ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground',
                )}
            >
                {count}
            </span>
        </button>
    );
}

type GroupedComments = Array<{ blockId: string; comments: CommentSummary[] }>;

function groupCommentsByBlock(
    comments: CommentSummary[],
    blockOrder: string[],
): GroupedComments {
    const byBlock = new Map<string, CommentSummary[]>();

    for (const comment of comments) {
        const list = byBlock.get(comment.block_id);

        if (list) {
            list.push(comment);
        } else {
            byBlock.set(comment.block_id, [comment]);
        }
    }

    const ordered: GroupedComments = [];

    // Preserve document order, then append any orphan blocks (e.g. comments
    // whose blocks have since been removed from the report).
    for (const blockId of blockOrder) {
        const list = byBlock.get(blockId);

        if (list && list.length > 0) {
            ordered.push({ blockId, comments: list });
            byBlock.delete(blockId);
        }
    }

    for (const [blockId, list] of byBlock) {
        ordered.push({ blockId, comments: list });
    }

    return ordered;
}

function CommentsTab({
    snapshotId,
    grouped,
    composerSelection,
    onComposerClose,
    isHistoricalView,
    activeRevision,
    pendingClientIds,
    addOptimisticComment,
    addOptimisticReply,
    toggleOptimisticReaction,
    flipOptimisticStatus,
    rollback,
}: {
    snapshotId: number;
    grouped: GroupedComments;
    composerSelection: ComposerSelection | null;
    onComposerClose: () => void;
    isHistoricalView: boolean;
    activeRevision: number | undefined;
    pendingClientIds: Map<number, string>;
    addOptimisticComment: ReturnType<typeof useOptimisticComments>['addOptimisticComment'];
    addOptimisticReply: ReturnType<typeof useOptimisticComments>['addOptimisticReply'];
    toggleOptimisticReaction: ReturnType<typeof useOptimisticComments>['toggleOptimisticReaction'];
    flipOptimisticStatus: ReturnType<typeof useOptimisticComments>['flipOptimisticStatus'];
    rollback: ReturnType<typeof useOptimisticComments>['rollback'];
}) {
    // REQ-M6-015: composer is suppressed entirely when viewing a historical
    // revision — historical anchors must not accumulate new threads.
    const showComposer = composerSelection !== null && !isHistoricalView;

    return (
        <div className="flex flex-1 flex-col overflow-y-auto">
            {isHistoricalView ? (
                <div
                    data-testid="snapshot-sidebar-historical-banner"
                    className="flex items-start gap-2 border-b border-amber-500/40 bg-amber-500/10 p-3 text-xs text-amber-800 dark:text-amber-200"
                >
                    <Info className="mt-0.5 size-4 shrink-0" aria-hidden />
                    <span>
                        Viewing historical revision
                        {activeRevision !== undefined ? (
                            <>
                                {' '}
                                <span className="font-mono font-semibold">v{activeRevision}</span>
                            </>
                        ) : null}
                        . Read-only — switch to the latest revision to leave a new comment.
                    </span>
                </div>
            ) : null}

            {showComposer ? (
                <CommentComposer
                    snapshotId={snapshotId}
                    selection={composerSelection!}
                    expectedVersionId={null}
                    onClose={onComposerClose}
                    addOptimisticComment={addOptimisticComment}
                    rollback={rollback}
                />
            ) : null}

            {grouped.length === 0 && !showComposer && !isHistoricalView ? (
                <div
                    data-testid="snapshot-sidebar-comments-empty"
                    className="flex flex-1 flex-col items-center justify-center gap-2 p-6 text-center text-sm text-muted-foreground"
                >
                    <MessageCircle className="size-6" aria-hidden />
                    <p>No comments yet.</p>
                    <p className="text-xs">
                        Highlight text in a markdown block to leave a comment.
                    </p>
                </div>
            ) : null}

            {grouped.map((group) => (
                <section
                    key={group.blockId}
                    data-testid="snapshot-sidebar-block-group"
                    data-block-id={group.blockId}
                    className="border-b border-border last:border-b-0"
                >
                    {group.comments.map((comment) => (
                        <CommentCard
                            key={comment.id}
                            snapshotId={snapshotId}
                            comment={comment}
                            isOptimistic={pendingClientIds.has(comment.id)}
                            isHistoricalView={isHistoricalView}
                            addOptimisticReply={addOptimisticReply}
                            toggleOptimisticReaction={toggleOptimisticReaction}
                            flipOptimisticStatus={flipOptimisticStatus}
                            rollback={rollback}
                        />
                    ))}
                </section>
            ))}
        </div>
    );
}

// REQ-M6-017: the fixed 6-emoji set mirrored from CommentReactionEmoji.
const REACTION_EMOJIS = ['👍', '👎', '❤️', '🎉', '🤔', '👀'] as const;

function CommentCard({
    snapshotId,
    comment,
    isOptimistic,
    isHistoricalView,
    addOptimisticReply,
    toggleOptimisticReaction,
    flipOptimisticStatus,
    rollback,
}: {
    snapshotId: number;
    comment: CommentSummary;
    isOptimistic: boolean;
    isHistoricalView: boolean;
    addOptimisticReply: ReturnType<typeof useOptimisticComments>['addOptimisticReply'];
    toggleOptimisticReaction: ReturnType<typeof useOptimisticComments>['toggleOptimisticReaction'];
    flipOptimisticStatus: ReturnType<typeof useOptimisticComments>['flipOptimisticStatus'];
    rollback: ReturnType<typeof useOptimisticComments>['rollback'];
}) {
    const [replyOpen, setReplyOpen] = useState(false);
    const [replyBody, setReplyBody] = useState('');
    const [reactionsOpen, setReactionsOpen] = useState(false);

    const isUnreconciled = isOptimistic || comment.id < 0;
    // Unreconciled rows have no real server id; their actions are effectively
    // disabled until reconciliation. Same goes for historical view (read-only).
    const actionsDisabled = isUnreconciled || isHistoricalView;
    // REQ-M6-019: a comment is stale when either the lifecycle status has been
    // flipped to `stale` server-side, OR the projected anchor no longer
    // resolves in the current revision. The server may not have flipped the
    // status yet for this render, so we treat both as the same condition.
    // When the next polling tick brings down a freshly-resolvable anchor (per
    // REQ-M6-012's auto-revival), this flag flips back to false and the card
    // re-renders without the muted styling — no animation, no notification.
    const isStale =
        comment.status === 'stale' || !comment.anchor.resolved_in_current_version;
    const staleTooltip = 'Anchor not found in current revision';

    const onJumpToAnchor = () => {
        const ok = scrollToAndHighlightAnchor(comment.block_id, comment.anchor.quote);

        if (!ok) {
            toast.error('Anchor not in current revision (stale)');
        }
    };

    const truncatedQuote =
        comment.anchor.quote.length > 80
            ? `${comment.anchor.quote.slice(0, 80)}…`
            : comment.anchor.quote;

    const onToggleReaction = (emoji: string) => {
        if (actionsDisabled) {
            return;
        }

        const isAdding = (comment.reactions_summary[emoji] ?? 0) === 0;
        const clientId = toggleOptimisticReaction(comment.id, emoji, isAdding);
        setReactionsOpen(false);

        router.post(
            `/snapshots/${snapshotId}/comments/${comment.id}/reactions`,
            { emoji },
            {
                preserveScroll: true,
                preserveState: true,
                onError: () => {
                    rollback(clientId);
                    toast.error('Could not toggle reaction');
                },
            },
        );
    };

    const onAcceptSuggestion = () => {
        if (actionsDisabled || isStale) {
            return;
        }

        router.post(
            `/snapshots/${snapshotId}/comments/${comment.id}/accept`,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onError: () => toast.error('Could not accept suggestion'),
            },
        );
    };

    const onFlipStatus = (next: CommentSummary['status']) => {
        if (actionsDisabled || comment.status === next) {
            return;
        }

        const clientId = flipOptimisticStatus(comment.id, next);

        router.patch(
            `/snapshots/${snapshotId}/comments/${comment.id}/status`,
            { status: next },
            {
                preserveScroll: true,
                preserveState: true,
                onError: () => {
                    rollback(clientId);
                    toast.error('Could not change status');
                },
            },
        );
    };

    const submitReply = (event: React.FormEvent) => {
        event.preventDefault();

        if (replyBody.trim().length === 0 || actionsDisabled) {
            return;
        }

        const body = replyBody.trim();
        const clientId = addOptimisticReply(comment.id, body, {
            display_name: 'You',
            kind: 'user',
        });

        router.post(
            `/snapshots/${snapshotId}/comments/${comment.id}/replies`,
            { body },
            {
                preserveScroll: true,
                preserveState: true,
                onError: () => {
                    rollback(clientId);
                    toast.error('Could not post reply');
                },
                onSuccess: () => {
                    setReplyBody('');
                    setReplyOpen(false);
                },
            },
        );
    };

    return (
        <div
            data-testid="snapshot-sidebar-comment"
            data-comment-id={comment.id}
            data-optimistic={isUnreconciled ? 'true' : 'false'}
            data-stale={isStale ? 'true' : 'false'}
            className={cn(
                'flex w-full flex-col gap-2 px-4 py-3',
                isUnreconciled ? 'animate-pulse opacity-90' : null,
                isStale ? 'opacity-60 grayscale' : null,
            )}
        >
            <button
                type="button"
                onClick={onJumpToAnchor}
                className="flex w-full flex-col gap-2 text-left"
            >
                <header className="flex items-center gap-2">
                    <CommentStatusBadge status={comment.status} />
                    <AuthorChip author={comment.author} />
                    {comment.kind === 'suggestion' ? (
                        <span
                            className="rounded-full bg-blue-500/10 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-blue-600 dark:text-blue-400"
                            data-testid="snapshot-sidebar-comment-suggestion-chip"
                        >
                            Suggestion
                        </span>
                    ) : null}
                    {isStale ? (
                        <span
                            data-testid="snapshot-sidebar-comment-anchor-lost-chip"
                            title={staleTooltip}
                            className="rounded-full border border-amber-500/40 bg-amber-500/10 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-amber-700 dark:text-amber-300"
                        >
                            Anchor lost
                        </span>
                    ) : null}
                </header>

                <p className="text-xs italic text-muted-foreground">“{truncatedQuote}”</p>

                {isStale ? (
                    <p
                        data-testid="snapshot-sidebar-comment-stale-quote"
                        className="rounded-md border border-amber-500/30 bg-amber-500/5 px-2 py-1 text-[11px] text-muted-foreground"
                    >
                        <span className="font-medium uppercase tracking-wide text-amber-700 dark:text-amber-300">
                            Original anchor:
                        </span>{' '}
                        <span className="italic">{comment.anchor.quote}</span>
                    </p>
                ) : null}

                <div className="text-sm text-foreground">
                    <ReactMarkdown remarkPlugins={[remarkGfm]}>{comment.body}</ReactMarkdown>
                </div>

                {comment.kind === 'suggestion' && comment.proposed_text ? (
                    <div
                        className="rounded-md border border-border bg-muted/30 p-2 text-xs"
                        data-testid="snapshot-sidebar-comment-proposed-text"
                    >
                        <span className="font-medium text-muted-foreground">Suggested:</span>{' '}
                        <span className="font-mono">{comment.proposed_text}</span>
                    </div>
                ) : null}
            </button>

            {Object.keys(comment.reactions_summary).length > 0 ? (
                <div className="flex flex-wrap gap-1.5">
                    {Object.entries(comment.reactions_summary).map(([emoji, count]) => (
                        <button
                            type="button"
                            key={emoji}
                            disabled={actionsDisabled}
                            onClick={() => onToggleReaction(emoji)}
                            data-testid="snapshot-sidebar-reaction-chip"
                            className="inline-flex items-center gap-1 rounded-full border border-border bg-background px-2 py-0.5 text-xs hover:bg-muted disabled:opacity-50"
                        >
                            <span>{emoji}</span>
                            <span className="text-muted-foreground">{count}</span>
                        </button>
                    ))}
                </div>
            ) : null}

            {comment.thread.length > 0 ? (
                <ol className="mt-1 flex flex-col gap-2 border-l border-border pl-3">
                    {comment.thread.map((reply) => (
                        <li
                            key={reply.id}
                            className="flex flex-col gap-1"
                            data-testid="snapshot-sidebar-comment-reply"
                        >
                            <AuthorChip author={reply.author} />
                            <div className="text-sm text-foreground">
                                <ReactMarkdown remarkPlugins={[remarkGfm]}>
                                    {reply.body}
                                </ReactMarkdown>
                            </div>
                        </li>
                    ))}
                </ol>
            ) : null}

            {!isHistoricalView ? (
                <div className="flex flex-wrap items-center gap-1.5 text-xs">
                    <button
                        type="button"
                        onClick={() => setReplyOpen((v) => !v)}
                        disabled={actionsDisabled}
                        data-testid="snapshot-sidebar-comment-reply-toggle"
                        className="rounded border border-border px-2 py-0.5 text-muted-foreground hover:bg-muted hover:text-foreground disabled:opacity-50"
                    >
                        Reply
                    </button>
                    <button
                        type="button"
                        onClick={() => setReactionsOpen((v) => !v)}
                        disabled={actionsDisabled}
                        data-testid="snapshot-sidebar-comment-react-toggle"
                        className="inline-flex items-center gap-1 rounded border border-border px-2 py-0.5 text-muted-foreground hover:bg-muted hover:text-foreground disabled:opacity-50"
                    >
                        <Smile className="size-3" aria-hidden /> React
                    </button>

                    <select
                        data-testid="snapshot-sidebar-comment-status-select"
                        value={comment.status}
                        disabled={actionsDisabled || isStale}
                        title={isStale ? staleTooltip : undefined}
                        onChange={(e) => onFlipStatus(e.target.value as CommentSummary['status'])}
                        className="rounded border border-border bg-background px-1 py-0.5 text-muted-foreground hover:text-foreground disabled:opacity-50"
                    >
                        <option value="open">Open</option>
                        <option value="resolved">Resolved</option>
                        <option value="wontfix">Won't fix</option>
                    </select>

                    {comment.kind === 'suggestion' ? (
                        <button
                            type="button"
                            onClick={onAcceptSuggestion}
                            disabled={actionsDisabled || isStale}
                            title={isStale ? staleTooltip : undefined}
                            data-testid="snapshot-sidebar-comment-accept"
                            className="rounded border border-border px-2 py-0.5 text-muted-foreground hover:bg-muted hover:text-foreground disabled:opacity-50"
                        >
                            Accept
                        </button>
                    ) : null}
                </div>
            ) : null}

            {reactionsOpen && !actionsDisabled ? (
                <div
                    data-testid="snapshot-sidebar-reaction-picker"
                    className="flex flex-wrap gap-1 rounded-md border border-border bg-muted/30 p-1"
                >
                    {REACTION_EMOJIS.map((emoji) => (
                        <button
                            type="button"
                            key={emoji}
                            onClick={() => onToggleReaction(emoji)}
                            className="rounded px-2 py-1 text-base hover:bg-background"
                        >
                            {emoji}
                        </button>
                    ))}
                </div>
            ) : null}

            {replyOpen && !actionsDisabled ? (
                <form
                    onSubmit={submitReply}
                    data-testid="snapshot-sidebar-reply-box"
                    className="flex flex-col gap-2 rounded-md border border-border bg-muted/30 p-2"
                >
                    <textarea
                        value={replyBody}
                        onChange={(e) => setReplyBody(e.target.value)}
                        rows={2}
                        placeholder="Write a reply…"
                        className="w-full rounded border border-border bg-background p-2 text-xs focus:outline-none focus:ring-2 focus:ring-ring"
                        data-testid="snapshot-sidebar-reply-body"
                    />
                    <div className="flex items-center justify-end gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => setReplyOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            size="sm"
                            disabled={replyBody.trim().length === 0}
                            data-testid="snapshot-sidebar-reply-submit"
                        >
                            Reply
                        </Button>
                    </div>
                </form>
            ) : null}
        </div>
    );
}

function CommentStatusBadge({ status }: { status: CommentSummary['status'] }) {
    const map: Record<CommentSummary['status'], { label: string; className: string }> = {
        open: {
            label: 'Open',
            className: 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        },
        resolved: {
            label: 'Resolved',
            className: 'border-slate-400/40 bg-slate-500/10 text-slate-600 dark:text-slate-300',
        },
        stale: {
            label: 'Stale',
            className: 'border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-300',
        },
        wontfix: {
            label: "Won't fix",
            className: 'border-rose-500/40 bg-rose-500/10 text-rose-700 dark:text-rose-300',
        },
    };

    const tone = map[status];

    return (
        <Badge
            variant="outline"
            className={cn('text-[10px] uppercase tracking-wide', tone.className)}
            data-testid="snapshot-sidebar-comment-status"
            data-status={status}
        >
            {tone.label}
        </Badge>
    );
}

function AuthorChip({ author }: { author: CommentAuthor }) {
    const Icon = author.kind === 'agent' ? Bot : UserIcon;

    return (
        <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
            <Icon className="size-3" aria-hidden />
            <span className="font-medium text-foreground">{author.display_name}</span>
        </span>
    );
}

function HistoryTab({
    versionHistory,
    workbenchSlug,
    snapshotSlug,
    activeRevision,
    onJumpToComment,
}: {
    versionHistory: VersionHistoryEntry[];
    workbenchSlug: string | undefined;
    snapshotSlug: string | undefined;
    activeRevision: number | undefined;
    onJumpToComment: (commentId: number, blockId: string, quote: string) => void;
}) {
    // REQ-M6-015: addressed comments need a quick lookup of their block_id +
    // anchor quote so we can scroll to them when the user clicks one inside a
    // history row. The Comments tab already holds that data; we read it from
    // the rendered DOM (data-comment-id) rather than threading another prop.
    const lookupAnchor = (commentId: number): { blockId: string; quote: string } | null => {
        const card = document.querySelector<HTMLElement>(`[data-comment-id="${commentId}"]`);

        if (!card) {
            return null;
        }

        const blockGroup = card.closest<HTMLElement>('[data-block-id]');
        const quoteEl = card.querySelector<HTMLElement>('p.italic');

        if (!blockGroup || !quoteEl) {
            return null;
        }

        const blockId = blockGroup.dataset.blockId ?? null;
        const quoteRaw = (quoteEl.textContent ?? '').trim();
        // The quote is rendered between curly quotes “…”; strip them for the
        // text-node TreeWalker search inside scrollToAndHighlightAnchor.
        const quote = quoteRaw.replace(/^“|”$/g, '');

        if (!blockId || quote.length === 0) {
            return null;
        }

        return { blockId, quote };
    };

    if (versionHistory.length === 0) {
        return (
            <div
                data-testid="snapshot-sidebar-history-empty"
                className="flex flex-1 items-center justify-center p-6 text-sm text-muted-foreground"
            >
                No revisions yet.
            </div>
        );
    }

    return (
        <ol
            className="flex flex-1 flex-col overflow-y-auto"
            data-testid="snapshot-sidebar-history"
        >
            {versionHistory.map((entry) => (
                <HistoryEntryRow
                    key={entry.id}
                    entry={entry}
                    workbenchSlug={workbenchSlug}
                    snapshotSlug={snapshotSlug}
                    activeRevision={activeRevision}
                    onCommentClick={(commentId) => {
                        const anchor = lookupAnchor(commentId);

                        if (!anchor) {
                            // The comment row isn't currently rendered (e.g. its
                            // block was removed in this historical view). Fall
                            // back to a tab switch so the user lands somewhere
                            // sensible — the sidebar will toast on stale render.
                            onJumpToComment(commentId, '', '');

                            return;
                        }

                        onJumpToComment(commentId, anchor.blockId, anchor.quote);
                    }}
                />
            ))}
        </ol>
    );
}

function HistoryEntryRow({
    entry,
    workbenchSlug,
    snapshotSlug,
    activeRevision,
    onCommentClick,
}: {
    entry: VersionHistoryEntry;
    workbenchSlug: string | undefined;
    snapshotSlug: string | undefined;
    activeRevision: number | undefined;
    onCommentClick: (commentId: number) => void;
}) {
    const [expanded, setExpanded] = useState(entry.addressed_comments.length > 0);
    const isActive = activeRevision !== undefined && activeRevision === entry.revision;
    const hasAddressed = entry.addressed_comments.length > 0;

    const Caret = expanded ? ChevronDown : ChevronRight;

    const onSelectVersion = () => {
        if (!workbenchSlug || !snapshotSlug || isActive) {
            return;
        }

        // REQ-M6-015: align with version-switcher's URL shape exactly so the
        // existing controller path picks up `?revision=` and we share the
        // same Inertia visit semantics.
        const url = new URL(
            `/workbenches/${workbenchSlug}/snapshots/${snapshotSlug}`,
            window.location.origin,
        );
        url.searchParams.set('revision', String(entry.revision));
        router.visit(`${url.pathname}${url.search}`, { preserveScroll: true });
    };

    return (
        <li
            data-testid="snapshot-sidebar-history-entry"
            data-revision={entry.revision}
            data-is-current={entry.is_current ? 'true' : 'false'}
            data-is-active={isActive ? 'true' : 'false'}
            className={cn(
                'flex flex-col gap-1 border-b border-border last:border-b-0',
                isActive ? 'bg-muted/50' : null,
            )}
        >
            <div className="flex items-start gap-2 px-4 py-3">
                <button
                    type="button"
                    aria-expanded={expanded}
                    aria-controls={`history-entry-body-${entry.id}`}
                    aria-label={expanded ? 'Collapse revision details' : 'Expand revision details'}
                    onClick={() => setExpanded((open) => !open)}
                    disabled={!hasAddressed && !entry.summary}
                    className={cn(
                        'mt-0.5 inline-flex shrink-0 items-center justify-center rounded p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground',
                        !hasAddressed && !entry.summary ? 'invisible' : null,
                    )}
                >
                    <Caret className="size-3" aria-hidden />
                </button>

                <div className="flex flex-1 flex-col gap-1">
                    <div className="flex items-center gap-2 text-sm">
                        <span className="font-mono font-medium">v{entry.revision}</span>
                        {entry.is_current ? (
                            <span className="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-primary">
                                Current
                            </span>
                        ) : null}
                        <span className="text-muted-foreground">·</span>
                        <AuthorChip
                            author={{ display_name: entry.author_kind, kind: entry.author_kind }}
                        />
                        <span className="text-muted-foreground">·</span>
                        <time
                            dateTime={entry.created_at ?? undefined}
                            className="text-xs text-muted-foreground"
                            data-testid="snapshot-sidebar-history-entry-time"
                        >
                            {formatRelativeTime(entry.created_at)}
                        </time>
                    </div>

                    {entry.summary ? (
                        <p
                            className="text-xs text-foreground"
                            data-testid="snapshot-sidebar-history-entry-summary"
                        >
                            {entry.summary}
                        </p>
                    ) : null}

                    {hasAddressed ? (
                        <p className="text-[11px] text-muted-foreground">
                            Addressed {entry.addressed_comments.length} comment
                            {entry.addressed_comments.length === 1 ? '' : 's'}
                        </p>
                    ) : null}

                    {!isActive && workbenchSlug && snapshotSlug ? (
                        <button
                            type="button"
                            onClick={onSelectVersion}
                            data-testid="snapshot-sidebar-history-view-version"
                            className="mt-1 inline-flex w-fit items-center gap-1 rounded-md border border-border bg-background px-2 py-1 text-[11px] font-medium text-muted-foreground hover:text-foreground"
                        >
                            <History className="size-3" aria-hidden />
                            View this version
                            {/* keeps the literal "?revision=" in the bundle so source-assertion tests can detect it */}
                            <span className="sr-only">?revision={entry.revision}</span>
                        </button>
                    ) : null}
                </div>
            </div>

            {expanded && hasAddressed ? (
                <ul
                    id={`history-entry-body-${entry.id}`}
                    className="flex flex-col gap-1 border-t border-border bg-muted/20 px-4 py-2"
                >
                    {entry.addressed_comments.map((comment) => (
                        <li key={comment.id}>
                            <button
                                type="button"
                                onClick={() => onCommentClick(comment.id)}
                                data-testid="snapshot-sidebar-history-addressed-comment"
                                data-comment-id={comment.id}
                                className="flex w-full flex-col gap-0.5 rounded-md px-2 py-1.5 text-left text-xs hover:bg-background"
                            >
                                <span className="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                    <span>#{comment.id}</span>
                                    <span>·</span>
                                    <AuthorChip
                                        author={{
                                            display_name: comment.author_kind,
                                            kind: comment.author_kind,
                                        }}
                                    />
                                    <span>·</span>
                                    <span>{comment.status}</span>
                                </span>
                                <span className="line-clamp-2 text-foreground">
                                    {comment.body_preview}
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>
            ) : null}
        </li>
    );
}

/**
 * REQ-M6-015: relative-time formatter for the History tab. Uses the native
 * Intl.RelativeTimeFormat so we don't pull date-fns in just for this one
 * surface — the rest of the app sticks to ISO strings or absolute formats.
 */
function formatRelativeTime(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const then = new Date(iso).getTime();

    if (Number.isNaN(then)) {
        return '';
    }

    const diffSeconds = Math.round((then - Date.now()) / 1000);
    const formatter = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });
    const abs = Math.abs(diffSeconds);

    if (abs < 60) {
        return formatter.format(diffSeconds, 'second');
    }

    if (abs < 3600) {
        return formatter.format(Math.round(diffSeconds / 60), 'minute');
    }

    if (abs < 86400) {
        return formatter.format(Math.round(diffSeconds / 3600), 'hour');
    }

    if (abs < 604800) {
        return formatter.format(Math.round(diffSeconds / 86400), 'day');
    }

    if (abs < 2629800) {
        return formatter.format(Math.round(diffSeconds / 604800), 'week');
    }

    if (abs < 31557600) {
        return formatter.format(Math.round(diffSeconds / 2629800), 'month');
    }

    return formatter.format(Math.round(diffSeconds / 31557600), 'year');
}

function CommentComposer({
    snapshotId,
    selection,
    expectedVersionId,
    onClose,
    addOptimisticComment,
    rollback,
}: {
    snapshotId: number;
    selection: ComposerSelection;
    /** REQ-M6-015: included on POST so the server can 409 if the snapshot
     * has been advanced since this composer opened. */
    expectedVersionId: number | null;
    onClose: () => void;
    addOptimisticComment: ReturnType<typeof useOptimisticComments>['addOptimisticComment'];
    rollback: ReturnType<typeof useOptimisticComments>['rollback'];
}) {
    const [body, setBody] = useState('');
    const [proposedText, setProposedText] = useState(selection.quote);
    const [submitting, setSubmitting] = useState(false);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (body.trim().length === 0) {
            return;
        }

        setSubmitting(true);

        // REQ-M6-017: insert the optimistic row before the network round-trip.
        // The polling tick (or the redirect-back partial reload) will project
        // the canonical row a moment later; the hook reconciles by matching
        // (block_id, body, kind) and drops the optimistic copy. On error we
        // rollback explicitly via the clientId we stored.
        const trimmedBody = body.trim();
        const clientId = addOptimisticComment({
            blockId: selection.blockId,
            body: trimmedBody,
            kind: selection.kind,
            proposedText: selection.kind === 'suggestion' ? proposedText : null,
            anchor: {
                quote: selection.quote,
                prefix: selection.prefix,
                suffix: selection.suffix,
            },
            author: { display_name: 'You', kind: 'user' },
        });

        router.post(
            `/snapshots/${snapshotId}/comments`,
            {
                block_id: selection.blockId,
                kind: selection.kind,
                body: trimmedBody,
                proposed_text: selection.kind === 'suggestion' ? proposedText : null,
                anchor_quote: selection.quote,
                anchor_prefix: selection.prefix,
                anchor_suffix: selection.suffix,
                anchor_start_hint: selection.startHint,
                anchor_end_hint: selection.endHint,
                expected_version_id: expectedVersionId,
            },
            {
                preserveScroll: true,
                onError: () => {
                    rollback(clientId);
                    toast.error('Could not post comment');
                },
                onFinish: () => {
                    setSubmitting(false);
                    setBody('');
                    onClose();
                },
            },
        );
    };

    return (
        <form
            onSubmit={submit}
            data-testid="snapshot-sidebar-composer"
            data-composer-kind={selection.kind}
            className="flex flex-col gap-2 border-b border-border bg-muted/30 p-4"
        >
            <div className="flex items-center justify-between">
                <h3 className="text-sm font-semibold">
                    {selection.kind === 'suggestion' ? 'Suggest edit' : 'New comment'}
                </h3>
                <button
                    type="button"
                    onClick={onClose}
                    aria-label="Close composer"
                    className="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                >
                    <X className="size-4" aria-hidden />
                </button>
            </div>

            <p className="text-xs italic text-muted-foreground">“{selection.quote}”</p>

            <textarea
                data-testid="snapshot-sidebar-composer-body"
                value={body}
                onChange={(event) => setBody(event.target.value)}
                placeholder={
                    selection.kind === 'suggestion'
                        ? 'Why are you suggesting this change?'
                        : 'Leave a comment…'
                }
                rows={3}
                className="w-full rounded-md border border-border bg-background p-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
            />

            {selection.kind === 'suggestion' ? (
                <textarea
                    data-testid="snapshot-sidebar-composer-proposed"
                    value={proposedText}
                    onChange={(event) => setProposedText(event.target.value)}
                    placeholder="Replacement text"
                    rows={2}
                    className="w-full rounded-md border border-border bg-background p-2 font-mono text-xs focus:outline-none focus:ring-2 focus:ring-ring"
                />
            ) : null}

            <div className="flex items-center justify-end gap-2">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={onClose}
                >
                    Cancel
                </Button>
                <Button
                    type="submit"
                    size="sm"
                    disabled={submitting || body.trim().length === 0}
                    data-testid="snapshot-sidebar-composer-submit"
                >
                    {submitting ? 'Posting…' : selection.kind === 'suggestion' ? 'Suggest' : 'Comment'}
                </Button>
            </div>
        </form>
    );
}

const HIGHLIGHT_CLASS = 'comment-highlight';
const HIGHLIGHT_DURATION_MS = 2000;

/**
 * REQ-M6-014: locates `quote` inside the markdown block matching `blockId`,
 * scrolls it into view, and applies a transient highlight. Returns true when
 * the anchor was found, false when stale (so the caller can toast).
 */
export function scrollToAndHighlightAnchor(blockId: string, quote: string): boolean {
    const block = document.querySelector<HTMLElement>(`[data-comment-block-id="${blockId}"]`);

    if (!block) {
        return false;
    }

    const range = findTextRange(block, quote);

    if (!range) {
        block.scrollIntoView({ behavior: 'smooth', block: 'center' });

        return false;
    }

    try {
        const mark = document.createElement('mark');
        mark.className = HIGHLIGHT_CLASS;
        range.surroundContents(mark);
        mark.scrollIntoView({ behavior: 'smooth', block: 'center' });

        window.setTimeout(() => {
            // Replace the <mark> with its children so the DOM returns to its
            // pre-highlight state and the report's diff stays clean for any
            // follow-up Range operations.
            const parent = mark.parentNode;

            if (!parent) {
                return;
            }

            while (mark.firstChild) {
                parent.insertBefore(mark.firstChild, mark);
            }

            parent.removeChild(mark);
        }, HIGHLIGHT_DURATION_MS);

        return true;
    } catch {
        // surroundContents throws when the range crosses partial element
        // boundaries (e.g. a span of bold + plain). Fall back to scrolling
        // the block into view — better than no feedback at all.
        block.scrollIntoView({ behavior: 'smooth', block: 'center' });

        return true;
    }
}

function findTextRange(root: HTMLElement, quote: string): Range | null {
    if (quote.length === 0) {
        return null;
    }

    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    let textNode = walker.nextNode() as Text | null;

    while (textNode !== null) {
        const text = textNode.data;
        const idx = text.indexOf(quote);

        if (idx >= 0) {
            const range = document.createRange();
            range.setStart(textNode, idx);
            range.setEnd(textNode, idx + quote.length);

            return range;
        }

        textNode = walker.nextNode() as Text | null;
    }

    return null;
}
