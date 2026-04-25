import { router } from '@inertiajs/react';
import { Bot, MessageCircle, User as UserIcon, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';

import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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

export type VersionHistoryEntry = {
    id: number;
    revision: number;
    view_type: string;
    author_kind: 'user' | 'agent';
    summary: string | null;
    addressed_comment_ids: number[];
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
};

type Tab = 'comments' | 'history';

export function SnapshotSidebar({
    snapshotId,
    comments,
    versionHistory,
    blockOrder,
    composerSelection,
    onComposerClose,
}: Props) {
    const [activeTab, setActiveTab] = useState<Tab>('comments');

    const grouped = useMemo(
        () => groupCommentsByBlock(comments, blockOrder),
        [comments, blockOrder],
    );

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
                />
            ) : (
                <HistoryTab versionHistory={versionHistory} />
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
}: {
    snapshotId: number;
    grouped: GroupedComments;
    composerSelection: ComposerSelection | null;
    onComposerClose: () => void;
}) {
    return (
        <div className="flex flex-1 flex-col overflow-y-auto">
            {composerSelection ? (
                <CommentComposer
                    snapshotId={snapshotId}
                    selection={composerSelection}
                    onClose={onComposerClose}
                />
            ) : null}

            {grouped.length === 0 && composerSelection === null ? (
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
                        <CommentCard key={comment.id} comment={comment} />
                    ))}
                </section>
            ))}
        </div>
    );
}

function CommentCard({ comment }: { comment: CommentSummary }) {
    const onClick = () => {
        const ok = scrollToAndHighlightAnchor(comment.block_id, comment.anchor.quote);

        if (!ok) {
            toast.error('Anchor not in current revision (stale)');
        }
    };

    const truncatedQuote =
        comment.anchor.quote.length > 80
            ? `${comment.anchor.quote.slice(0, 80)}…`
            : comment.anchor.quote;

    return (
        <button
            type="button"
            onClick={onClick}
            data-testid="snapshot-sidebar-comment"
            data-comment-id={comment.id}
            className="flex w-full flex-col gap-2 px-4 py-3 text-left hover:bg-muted/40"
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
            </header>

            <p className="text-xs italic text-muted-foreground">“{truncatedQuote}”</p>

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

            {Object.keys(comment.reactions_summary).length > 0 ? (
                <div className="flex flex-wrap gap-1.5">
                    {Object.entries(comment.reactions_summary).map(([emoji, count]) => (
                        <span
                            key={emoji}
                            className="inline-flex items-center gap-1 rounded-full border border-border bg-background px-2 py-0.5 text-xs"
                        >
                            <span>{emoji}</span>
                            <span className="text-muted-foreground">{count}</span>
                        </span>
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
        </button>
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

function HistoryTab({ versionHistory }: { versionHistory: VersionHistoryEntry[] }) {
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
        <ol className="flex flex-1 flex-col overflow-y-auto" data-testid="snapshot-sidebar-history">
            {versionHistory.map((entry) => (
                <li
                    key={entry.id}
                    data-testid="snapshot-sidebar-history-entry"
                    data-revision={entry.revision}
                    className="flex flex-col gap-1 border-b border-border px-4 py-3 last:border-b-0"
                >
                    <div className="flex items-center gap-2 text-sm">
                        <span className="font-mono font-medium">v{entry.revision}</span>
                        <span className="text-muted-foreground">·</span>
                        <AuthorChip
                            author={{ display_name: entry.author_kind, kind: entry.author_kind }}
                        />
                    </div>
                    {entry.summary ? (
                        <p className="text-xs text-muted-foreground">{entry.summary}</p>
                    ) : null}
                    {entry.addressed_comment_ids.length > 0 ? (
                        <p className="text-[11px] text-muted-foreground">
                            Addressed {entry.addressed_comment_ids.length} comment
                            {entry.addressed_comment_ids.length === 1 ? '' : 's'}
                        </p>
                    ) : null}
                </li>
            ))}
        </ol>
    );
}

function CommentComposer({
    snapshotId,
    selection,
    onClose,
}: {
    snapshotId: number;
    selection: ComposerSelection;
    onClose: () => void;
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

        router.post(
            `/snapshots/${snapshotId}/comments`,
            {
                block_id: selection.blockId,
                kind: selection.kind,
                body,
                proposed_text: selection.kind === 'suggestion' ? proposedText : null,
                anchor_quote: selection.quote,
                anchor_prefix: selection.prefix,
                anchor_suffix: selection.suffix,
                anchor_start_hint: selection.startHint,
                anchor_end_hint: selection.endHint,
            },
            {
                preserveScroll: true,
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
