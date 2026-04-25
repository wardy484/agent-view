import { ExternalLink } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import ReactMarkdown from 'react-markdown';
import type {Components} from 'react-markdown';
import remarkGfm from 'remark-gfm';

import { CommentSelectionMenu } from '@/components/nexus/comment-selection-menu';
import { CommentSelectionToolbar } from '@/components/nexus/comment-selection-toolbar';
import { FlowchartView } from '@/components/nexus/flowchart-view';
import type { FlowchartViewPayload } from '@/components/nexus/flowchart-view';
import { KanbanView } from '@/components/nexus/kanban-view';
import type { KanbanViewPayload } from '@/components/nexus/kanban-view';
import { ResponsiveSidebar } from '@/components/nexus/responsive-sidebar';
import { SlideDeckView } from '@/components/nexus/slide-deck-view';
import type { SlideDeckViewPayload } from '@/components/nexus/slide-deck-view';
import { SnapshotSidebar } from '@/components/nexus/snapshot-sidebar';
import type {
    CommentSummary,
    ComposerSelection,
    VersionHistoryEntry,
} from '@/components/nexus/snapshot-sidebar';
import { TableView } from '@/components/nexus/table-view';
import type { TableViewPayload } from '@/components/nexus/table-view';
import { useMarkdownSelection } from '@/hooks/use-markdown-selection';
import type { SelectionInfo } from '@/hooks/use-markdown-selection';
import { cn } from '@/lib/utils';

/**
 * REQ-M5-008: client renderer for report view_type. Blocks are iterated in
 * order and dispatched to:
 *  - markdown pipeline (react-markdown + remark-gfm) — same as SlideDeckView
 *  - an embed wrapper that delegates to the sibling view components
 *    (Table / Kanban / Flowchart / SlideDeck) in a read-only frame.
 *
 * Each embed shows a "v{pinned} · current v{latest}" badge. Clicking the
 * badge opens the embedded snapshot in a new tab. Selection inside reports
 * is disabled for M5 — the wrapper swallows pointer events on the view
 * children's selection affordances.
 */

export type MarkdownBlock = {
    type: 'markdown';
    body: string;
    /** REQ-M6-001 stable block id (uuid v4); optional for legacy payloads. */
    id?: string;
};

export type ResolvedEmbedBlock = {
    type: 'embed';
    snapshot_id: number;
    snapshot_slug?: string;
    workbench_slug?: string;
    title?: string;
    view_type?: string;
    pinned_version_id?: number;
    pinned_revision?: number;
    current_revision?: number;
    is_stale?: boolean;
    data_payload?: Record<string, unknown>;
    restricted?: boolean;
};

export type ReportBlock = MarkdownBlock | ResolvedEmbedBlock;

export type ReportViewPayload = {
    blocks?: ReportBlock[];
    resolved_blocks?: ReportBlock[];
};

type Props = {
    payload: ReportViewPayload;
    className?: string;
    fullBleed?: boolean;
    /** REQ-M6-014: pass-through for the sidebar's Comments tab. */
    snapshotId?: number;
    comments?: CommentSummary[] | null;
    versionHistory?: VersionHistoryEntry[] | null;
    /** REQ-M6-015: when true the page is rendering a historical revision —
     * disable the floating selection menu's write actions and the composer. */
    isHistoricalView?: boolean;
    workbenchSlug?: string;
    snapshotSlug?: string;
    /** REQ-M6-015: revision currently being rendered (for the History tab). */
    activeRevision?: number;
};

export function ReportView({
    payload,
    className,
    fullBleed = false,
    snapshotId,
    comments,
    versionHistory,
    isHistoricalView = false,
    workbenchSlug,
    snapshotSlug,
    activeRevision,
}: Props) {
    // Server inlines the resolved blocks; fall back to raw blocks so the
    // component still renders something useful if resolved_blocks is missing
    // (e.g. direct consumers outside the snapshot page).
    const blocks = useMemo(
        () => payload.resolved_blocks ?? payload.blocks ?? [],
        [payload.resolved_blocks, payload.blocks],
    );

    const containerRef = useRef<HTMLDivElement>(null);
    const selection = useMarkdownSelection(containerRef);
    const [composerSelection, setComposerSelection] = useState<ComposerSelection | null>(null);

    const blockOrder = useMemo(
        () =>
            blocks
                .filter((block): block is MarkdownBlock => block.type === 'markdown' && Boolean(block.id))
                .map((block) => block.id as string),
        [blocks],
    );

    if (blocks.length === 0) {
        return (
            <div
                data-testid="nexus-report-view"
                className={cn(
                    'rounded-lg border border-dashed border-border p-6 text-sm text-muted-foreground',
                    className,
                )}
            >
                This report has no blocks.
            </div>
        );
    }

    // REQ-M6-013: clear the active browser selection so the floating menu
    // unmounts. The hook's `selectionchange` listener picks up the empty
    // range and resets state.
    const clearSelection = () => {
        const sel = window.getSelection();

        if (sel) {
            sel.removeAllRanges();
        }
    };

    // REQ-M6-014: opening the composer carries the SelectionInfo into the
    // sidebar. Cross-block selections are rejected (blockId === null) — the
    // selection menu disables those actions, so we only need a defensive
    // guard here for keyboard-only invocation paths.
    //
    // REQ-M6-015: in historical view we suppress composer creation entirely
    // so a stray keyboard shortcut can't open one even though the floating
    // menu's Comment / Suggest buttons are also disabled.
    const openComposer = (kind: 'comment' | 'suggestion') => (info: SelectionInfo) => {
        if (info.blockId === null || isHistoricalView) {
            return;
        }

        setComposerSelection({
            blockId: info.blockId,
            quote: info.quote,
            prefix: info.prefix,
            suffix: info.suffix,
            startHint: info.startHint,
            endHint: info.endHint,
            kind,
        });
        clearSelection();
    };

    const handleComment = openComposer('comment');
    const handleSuggest = openComposer('suggestion');

    const sidebarVisible = comments !== undefined && comments !== null && snapshotId !== undefined;

    const reportBody = (
        <div
            ref={containerRef}
            data-testid="nexus-report-view"
            data-block-count={blocks.length}
            className={cn(
                'relative mx-auto flex w-full flex-col gap-6',
                fullBleed ? 'max-w-4xl px-6 py-10' : 'max-w-3xl',
                // REQ-M6-022: leave 80px (5rem) gap below the last
                // paragraph on mobile so the always-visible toolbar
                // (~72px tall + safe-area) never covers content.
                'pb-20 lg:pb-0',
                className,
            )}
        >
            {blocks.map((block, index) =>
                block.type === 'markdown' ? (
                    <MarkdownBlockView
                        key={block.id ?? index}
                        id={block.id}
                        body={block.body}
                    />
                ) : (
                    <EmbedBlockView key={index} block={block} />
                ),
            )}

            {/* REQ-M6-021: floating selection menu is desktop-only; touch
                screens get the sticky bottom toolbar instead. */}
            <div className="hidden lg:block">
                <CommentSelectionMenu
                    selection={selection}
                    onComment={handleComment}
                    onSuggest={handleSuggest}
                    onClose={clearSelection}
                    readOnly={isHistoricalView}
                />
            </div>

            <div className="lg:hidden">
                <CommentSelectionToolbar
                    selection={selection}
                    containerRef={containerRef}
                    onComment={handleComment}
                    onSuggest={handleSuggest}
                    onClose={clearSelection}
                    readOnly={isHistoricalView}
                />
            </div>
        </div>
    );

    if (!sidebarVisible) {
        return reportBody;
    }

    // REQ-M6-020: count of open root comments drives the mobile trigger badge.
    const openCommentCount = (comments ?? []).filter(
        (comment) => comment.status === 'open',
    ).length;

    const sidebar = (
        <SnapshotSidebar
            snapshotId={snapshotId as number}
            comments={comments ?? []}
            versionHistory={versionHistory ?? []}
            blockOrder={blockOrder}
            composerSelection={composerSelection}
            onComposerClose={() => setComposerSelection(null)}
            isHistoricalView={isHistoricalView}
            workbenchSlug={workbenchSlug}
            snapshotSlug={snapshotSlug}
            activeRevision={activeRevision}
        />
    );

    // REQ-M6-020: below `lg` the sidebar collapses into a slide-in sheet
    // triggered from the report column header. Above `lg` the sidebar
    // renders inline on the right (the unchanged REQ-M6-014 layout).
    return (
        <div
            className="flex w-full flex-col gap-0 lg:flex-row lg:items-start"
            data-testid="nexus-report-with-sidebar"
        >
            <div className="flex min-w-0 flex-1 flex-col">
                <ResponsiveSidebar openCommentCount={openCommentCount}>
                    {sidebar}
                </ResponsiveSidebar>
                {reportBody}
            </div>
            <div
                data-testid="snapshot-sidebar-desktop-inline"
                className="hidden shrink-0 lg:flex"
            >
                {sidebar}
            </div>
        </div>
    );
}

function MarkdownBlockView({ id, body }: { id?: string; body: string }) {
    const components = useMemo<Components>(() => buildMarkdownComponents(), []);

    return (
        <section
            data-testid="nexus-report-block"
            data-block-type="markdown"
            data-comment-block-id={id}
            className="report-prose text-base leading-relaxed text-foreground"
        >
            <ReactMarkdown remarkPlugins={[remarkGfm]} components={components}>
                {body}
            </ReactMarkdown>
        </section>
    );
}

function EmbedBlockView({ block }: { block: ResolvedEmbedBlock }) {
    const title = block.title ?? block.snapshot_slug ?? `Snapshot #${block.snapshot_id}`;

    if (block.restricted) {
        return (
            <section
                data-testid="nexus-report-block"
                data-block-type="embed"
                data-restricted="true"
                className="rounded-lg border border-dashed border-border bg-muted/30 p-4 text-sm text-muted-foreground"
            >
                <p className="font-medium text-foreground">Embed unavailable</p>
                <p className="mt-1">This embed is no longer accessible.</p>
            </section>
        );
    }

    const href =
        block.workbench_slug && block.snapshot_slug
            ? `/workbench/${block.workbench_slug}/${block.snapshot_slug}`
            : null;

    return (
        <section
            data-testid="nexus-report-block"
            data-block-type="embed"
            data-embedded-snapshot-id={block.snapshot_id}
            data-embedded-view-type={block.view_type}
            data-is-stale={block.is_stale ? 'true' : 'false'}
            className="flex flex-col gap-3 rounded-lg border border-border bg-background p-4 shadow-sm"
        >
            <header className="flex items-center justify-between gap-3">
                <div className="flex min-w-0 flex-col gap-1">
                    <div className="flex items-center gap-2">
                        <span className="truncate text-sm font-semibold text-foreground">
                            {title}
                        </span>
                        {block.view_type ? (
                            <span
                                className="inline-flex items-center rounded-full border border-border bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground"
                                data-view-type={block.view_type}
                            >
                                {block.view_type}
                            </span>
                        ) : null}
                    </div>
                    <RevisionBadge
                        pinned={block.pinned_revision}
                        current={block.current_revision}
                        isStale={block.is_stale}
                    />
                </div>
                {href ? (
                    <a
                        href={href}
                        target="_blank"
                        rel="noreferrer noopener"
                        className="inline-flex items-center gap-1 rounded-md border border-border px-2 py-1 text-xs text-muted-foreground hover:text-foreground"
                        data-testid="nexus-report-embed-open"
                    >
                        Open
                        <ExternalLink className="size-3" aria-hidden />
                    </a>
                ) : null}
            </header>

            {/* REQ-M5-008: selection is disabled inside reports for M5. The
                wrapper marks itself read-only and swallows pointer events on
                any selection affordances that bubble up from the child view. */}
            <div
                className="report-embed-frame pointer-events-auto"
                data-report-embed-readonly="true"
                aria-readonly="true"
            >
                <EmbeddedView block={block} />
            </div>
        </section>
    );
}

function EmbeddedView({ block }: { block: ResolvedEmbedBlock }) {
    const payload = (block.data_payload ?? {}) as Record<string, unknown>;

    if (block.view_type === 'table') {
        return <TableView payload={payload as TableViewPayload} fullBleed={false} />;
    }

    if (block.view_type === 'kanban') {
        return <KanbanView payload={payload as KanbanViewPayload} fullBleed={false} />;
    }

    if (block.view_type === 'flowchart') {
        return <FlowchartView payload={payload as FlowchartViewPayload} fullBleed={false} />;
    }

    if (block.view_type === 'slide_deck') {
        return <SlideDeckView payload={payload as SlideDeckViewPayload} mode="embedded" />;
    }

    return (
        <div className="rounded-md border border-dashed border-border p-4 text-xs text-muted-foreground">
            Embed renderer for view_type{' '}
            <code className="font-mono">{block.view_type}</code> is not implemented.
        </div>
    );
}

function RevisionBadge({
    pinned,
    current,
    isStale,
}: {
    pinned?: number;
    current?: number;
    isStale?: boolean;
}) {
    if (pinned === undefined) {
        return null;
    }

    return (
        <span
            className="text-xs text-muted-foreground"
            data-testid="nexus-report-embed-revision"
        >
            <span className="font-mono">v{pinned}</span>
            {current !== undefined && current !== pinned ? (
                <>
                    {' · '}
                    <span
                        className={cn(
                            'font-mono',
                            isStale ? 'text-amber-600 dark:text-amber-400' : 'text-muted-foreground',
                        )}
                    >
                        current v{current}
                    </span>
                </>
            ) : null}
        </span>
    );
}

type MdProps = {
    children?: React.ReactNode;
    className?: string;
    href?: string;
};

function buildMarkdownComponents(): Components {
    return {
        h1: ({ children }: MdProps) => (
            <h2 className="mt-2 mb-4 text-3xl font-semibold tracking-tight">{children}</h2>
        ),
        h2: ({ children }: MdProps) => (
            <h3 className="mt-2 mb-3 text-2xl font-semibold tracking-tight">{children}</h3>
        ),
        h3: ({ children }: MdProps) => (
            <h4 className="mt-2 mb-2 text-xl font-semibold">{children}</h4>
        ),
        p: ({ children }: MdProps) => <p className="mb-4 last:mb-0">{children}</p>,
        ul: ({ children }: MdProps) => (
            <ul className="mb-4 list-disc space-y-1 pl-6 last:mb-0">{children}</ul>
        ),
        ol: ({ children }: MdProps) => (
            <ol className="mb-4 list-decimal space-y-1 pl-6 last:mb-0">{children}</ol>
        ),
        li: ({ children }: MdProps) => <li className="pl-1">{children}</li>,
        strong: ({ children }: MdProps) => <strong className="font-semibold">{children}</strong>,
        em: ({ children }: MdProps) => <em className="italic">{children}</em>,
        a: ({ children, href }: MdProps) => (
            <a
                href={href}
                target="_blank"
                rel="noreferrer noopener"
                className="underline decoration-muted-foreground underline-offset-4 hover:decoration-current"
            >
                {children}
            </a>
        ),
        blockquote: ({ children }: MdProps) => (
            <blockquote className="my-4 border-l-2 border-border pl-4 italic text-muted-foreground">
                {children}
            </blockquote>
        ),
        hr: () => <hr className="my-6 border-border" />,
        code: ({ className, children, ...rest }: MdProps) => {
            const isBlock = /language-/.test(className ?? '');

            if (isBlock) {
                return (
                    <code
                        className={cn(
                            'block whitespace-pre font-mono text-sm leading-relaxed',
                            className,
                        )}
                        {...rest}
                    >
                        {children}
                    </code>
                );
            }

            return (
                <code
                    className="rounded bg-muted px-1.5 py-0.5 font-mono text-[0.9em]"
                    {...rest}
                >
                    {children}
                </code>
            );
        },
        pre: ({ children }: MdProps) => (
            <pre className="mb-4 overflow-x-auto rounded-lg border border-border bg-muted/40 p-4 text-sm last:mb-0">
                {children}
            </pre>
        ),
        table: ({ children }: MdProps) => (
            <div className="mb-4 overflow-x-auto rounded-lg border border-border bg-background shadow-sm last:mb-0">
                <table className="w-full border-collapse text-left text-sm">{children}</table>
            </div>
        ),
        thead: ({ children }: MdProps) => (
            <thead className="border-b border-border bg-muted/40">{children}</thead>
        ),
        tbody: ({ children }: MdProps) => <tbody>{children}</tbody>,
        tr: ({ children }: MdProps) => (
            <tr className="border-b border-border last:border-0">{children}</tr>
        ),
        th: ({ children }: MdProps) => (
            <th className="px-4 py-2 text-sm font-semibold text-foreground">{children}</th>
        ),
        td: ({ children }: MdProps) => (
            <td className="px-4 py-2 text-sm text-foreground">{children}</td>
        ),
    };
}
