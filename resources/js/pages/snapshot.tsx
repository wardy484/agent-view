import { Head, Link, router } from '@inertiajs/react';
import { Maximize2, Minimize2 } from 'lucide-react';
import { useEffect, useState } from 'react';

import { FlowchartView } from '@/components/nexus/flowchart-view';
import type { FlowchartViewPayload } from '@/components/nexus/flowchart-view';
import { KanbanView } from '@/components/nexus/kanban-view';
import type { KanbanViewPayload } from '@/components/nexus/kanban-view';
import { NewRevisionBanner } from '@/components/nexus/new-revision-banner';
import { PinToLatestToggle } from '@/components/nexus/pin-to-latest-toggle';
import { PreviewHomeButton } from '@/components/nexus/preview-home-button';
import { ReportView } from '@/components/nexus/report-view';
import type { ReportViewPayload } from '@/components/nexus/report-view';
import { ShareDialog } from '@/components/nexus/share-dialog';
import type {
    SnapshotShareSummary,
    SnapshotVisibility,
} from '@/components/nexus/share-dialog';
import { SlideDeckView } from '@/components/nexus/slide-deck-view';
import type { SlideDeckViewPayload } from '@/components/nexus/slide-deck-view';
import type {
    CommentSummary,
    VersionHistoryEntry,
} from '@/components/nexus/snapshot-sidebar';
import { TableView } from '@/components/nexus/table-view';
import type { TableViewPayload } from '@/components/nexus/table-view';
import { VersionSwitcher } from '@/components/nexus/version-switcher';
import type { SnapshotVersionSummary } from '@/components/nexus/version-switcher';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { Button } from '@/components/ui/button';
import { usePinToLatest } from '@/hooks/use-pin-to-latest';
import { useRevisionBanner } from '@/hooks/use-revision-banner';
import { useSidebarPolling } from '@/hooks/use-sidebar-polling';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';

type Workbench = {
    slug: string;
    name: string;
};

type Snapshot = {
    id: number;
    slug: string;
    title: string | null;
    current_version_id: number | null;
    // REQ-M6-016: monotonic counter on the snapshot row, bumped by every
    // comment / reply / reaction / resolution write. Drives the polling
    // loop's `?since=` cursor and the future M6-018 push banner.
    comments_revision?: number;
};

type Version = {
    id: number;
    revision: number;
    view_type: string;
    data_payload: TableViewPayload | Record<string, unknown>;
    metadata: Record<string, unknown> | null;
    // REQ-M5-007: report snapshots receive a server-resolved blocks array
    // so the React renderer doesn't fan out N HTTP calls per embed.
    resolved_blocks?: ReportViewPayload['resolved_blocks'];
};

type Mode = 'app' | 'preview';

type Props = {
    workbench: Workbench;
    snapshot: Snapshot;
    version: Version;
    versions: SnapshotVersionSummary[];
    mode: Mode;
    isAuthenticated: boolean;
    // REQ-M4-006: ownership / viewer-mode flags. Absent on pre-M4 payloads,
    // so we default to the most permissive shape for backwards compatibility.
    is_owner?: boolean;
    is_public_link?: boolean;
    // REQ-M4-010: Share-dialog props. Owners always receive these; non-owners
    // get safe defaults (visibility=private, empty shares, no URL).
    visibility?: SnapshotVisibility;
    share_url?: string | null;
    shares?: SnapshotShareSummary[];
    // REQ-M6-014: sidebar feeds. `comments` is null for unauthenticated link
    // viewers and for non-report views; `versionHistory` follows the same
    // gating so the History tab stays consistent.
    comments?: CommentSummary[] | null;
    versionHistory?: VersionHistoryEntry[] | null;
    // REQ-M6-015: true when ?revision= resolved to a non-current revision.
    // Drives the sidebar's read-only banner + composer suppression.
    is_historical_view?: boolean;
};

/**
 * REQ-M3-012 / REQ-M3-013:
 *  - mode === "app": wrap the snapshot in the standard app sidebar layout,
 *    show the workbench header (title, version switcher, fullscreen toggle).
 *  - mode === "preview": render bare, edge-to-edge, with only a subtle
 *    floating home link in the corner.
 *  - In-app fullscreen toggle hides the workbench header in place; ESC or a
 *    second click restores it.
 *
 * REQ-M4-006: strict read-only viewer mode. Non-owners (shared-with viewers
 * and public-link viewers) never see the version switcher or any mutation
 * affordance — we gate those on `is_owner && !is_public_link`.
 */
export default function SnapshotPage(props: Props) {
    const {
        mode,
        isAuthenticated,
        is_owner = true,
        is_public_link = false,
        visibility = 'private',
        share_url = null,
        shares = [],
        comments = null,
        versionHistory = null,
        is_historical_view = false,
    } = props;
    const isPreview = mode === 'preview';
    // Shared content (public links or shared-with viewers) defaults to
    // fullscreen so the snapshot takes the whole viewport without the
    // workbench chrome. Owners still start in the normal app shell.
    const isSharedView = is_public_link || !is_owner;
    const [isFullscreen, setIsFullscreen] = useState(isSharedView);

    // REQ-M6-016: poll the sidebar props every 8 seconds while the tab is
    // foregrounded. Only authenticated viewers on a current (non-historical)
    // revision opt in — public-link guests and historical views never poll.
    const shouldPoll =
        isAuthenticated && !is_public_link && !is_historical_view;
    useSidebarPolling(
        props.snapshot.id,
        props.version.revision,
        props.snapshot.comments_revision ?? 0,
        !shouldPoll,
    );

    // REQ-M6-018: capture the *initial* rendered revision once on mount via
    // a lazy useState initialiser so partial reloads that bring down a higher
    // `version.revision` don't silently mutate the comparison baseline. The
    // banner state diff-compares this captured value against the live prop.
    // (Lazy useState is the React-recommended ref-shaped pattern that's also
    // safe to read during render — useRef would lint as "ref accessed in
    // render".)
    const [renderedRevision] = useState<number>(() => props.version.revision);

    // "Pin to latest" auto-follows new revisions: when ON and an Echo push
    // arrives (or when the user landed on a historical revision), navigate
    // to the snapshot URL without `?revision=` so the server resolves to
    // current. Default ON for kanban (live ops view), OFF for everything
    // else. Per-snapshot, persisted in localStorage.
    const showPinToggle = isAuthenticated && !is_public_link && is_owner;
    const { pinned, setPinned } = usePinToLatest(
        props.snapshot.id,
        props.version.view_type,
    );

    const showBanner =
        isAuthenticated && !is_public_link && !is_historical_view && !pinned;
    const banner = useRevisionBanner(
        renderedRevision,
        props.version.revision,
        props.snapshot.slug,
        props.workbench.slug,
    );

    // REQ-M7-003: a brief fade-out / fade-in flag toggled when an Echo push
    // triggers a partial reload. We swap opacity for 200 ms so the user gets a
    // visual cue that the workbench just refreshed itself.
    const [isFading, setIsFading] = useState(false);

    // REQ-M7-003: subscribe to the snapshot's private broadcast channel so
    // SnapshotVersionAppended events trigger an Inertia partial reload of
    // just the bits that change (`snapshot`, `currentRevision`). Falls back
    // silently when Echo is unavailable — Reverb is an optimisation, not a
    // hard dependency. The 8-second poll path (REQ-M6-016, wired above via
    // useSidebarPolling) covers that case unconditionally, so this effect is
    // purely additive: when Echo is reachable we get instant updates, when it
    // is not the poll keeps the sidebar fresh on its 8-second cadence.
    const snapshotId = props.snapshot.id;
    const liveRevision = props.version.revision;
    const workbenchSlug = props.workbench.slug;
    const snapshotSlug = props.snapshot.slug;

    // When pinned and currently viewing a historical revision, jump to
    // latest. The server resolves the bare snapshot URL to current.
    useEffect(() => {
        if (pinned && is_historical_view) {
            router.visit(
                `/workbenches/${workbenchSlug}/snapshots/${snapshotSlug}`,
                {
                    preserveScroll: true,
                },
            );
        }
    }, [pinned, is_historical_view, workbenchSlug, snapshotSlug]);

    useEffect(() => {
        const echo = (
            window as {
                Echo?: {
                    private: (channel: string) => {
                        listen: (
                            event: string,
                            cb: (payload: { revision: number }) => void,
                        ) => unknown;
                    };
                    leave: (channel: string) => void;
                };
            }
        ).Echo;

        if (!echo) {
            // REQ-M6-016: poll fallback is already running unconditionally via
            // useSidebarPolling above, so when Echo is unavailable we simply
            // no-op here and let the 8-second loop carry the workbench.
            return;
        }

        const channelName = `snapshot.${snapshotId}`;

        try {
            const channel = echo.private(channelName);
            // prettier-ignore
            channel.listen('.SnapshotVersionAppended', (payload: { revision: number }) => {
                    if (
                        typeof payload?.revision === 'number' &&
                        payload.revision > liveRevision
                    ) {
                        setIsFading(true);

                        // Pinned: navigate to bare snapshot URL so the server
                        // resolves the latest revision (clears any ?revision=).
                        // Otherwise, partial reload preserves the current query
                        // string so historical viewers stay put.
                        const onFinish = () => {
                            window.setTimeout(() => setIsFading(false), 200);
                        };

                        if (pinned) {
                            router.visit(
                                `/workbenches/${workbenchSlug}/snapshots/${snapshotSlug}`,
                                {
                                    preserveScroll: true,
                                    onFinish,
                                },
                            );
                        } else {
                            router.reload({
                                only: ['snapshot', 'version', 'versions'],
                                onFinish,
                            });
                        }
                    }
                },
            );

            return () => {
                try {
                    echo.leave(`private-${channelName}`);
                } catch (error) {
                    // Defensive: leave should never throw, but swallow to keep
                    // unmount clean.
                    console.warn('[snapshot] Echo.leave failed', error);
                }
            };
        } catch (error) {
            // REQ-M6-016: Echo failed mid-subscribe — log and rely on the
            // useSidebarPolling 8-second fallback to keep the page fresh.
            console.warn(
                '[snapshot] Echo subscription failed; falling back to poll',
                error,
            );

            return;
        }
    }, [snapshotId, liveRevision, pinned, workbenchSlug, snapshotSlug]);

    // ESC exits in-app fullscreen mode. We deliberately don't intercept ESC
    // in pure preview mode — there's no chrome to restore.
    useEffect(() => {
        if (isPreview || !isFullscreen) {
            return;
        }

        function handleKeyDown(event: KeyboardEvent) {
            if (event.key === 'Escape') {
                setIsFullscreen(false);
            }
        }

        window.addEventListener('keydown', handleKeyDown);

        return () => {
            window.removeEventListener('keydown', handleKeyDown);
        };
    }, [isPreview, isFullscreen]);

    const showAppShell = !isPreview && !isFullscreen;
    const showWorkbenchHeader = !isPreview && !isFullscreen;
    const fullBleed = isPreview || isFullscreen;
    const showHomeButton = isPreview || isFullscreen;

    // REQ-M7-003: wrap the body in a 200 ms opacity transition so the swap
    // triggered by an Echo push is visually announced. `transition-opacity
    // duration-200` is the Tailwind primitive — no custom CSS file needed.
    const body = (
        <div
            data-testid="nexus-snapshot-fade"
            className={cn(
                'transition-opacity duration-200',
                isFading ? 'opacity-50' : 'opacity-100',
            )}
        >
            <SnapshotBody
                workbench={props.workbench}
                snapshot={props.snapshot}
                version={props.version}
                versions={props.versions}
                showWorkbenchHeader={showWorkbenchHeader}
                isFullscreen={isFullscreen}
                onToggleFullscreen={() =>
                    setIsFullscreen((current) => !current)
                }
                fullBleed={fullBleed}
                isOwner={is_owner}
                isPublicLink={is_public_link}
                visibility={visibility}
                shareUrl={share_url}
                shares={shares}
                comments={comments}
                versionHistory={versionHistory}
                isHistoricalView={is_historical_view}
                showPinToggle={showPinToggle}
                pinned={pinned}
                onTogglePinned={() => setPinned(!pinned)}
                onSelectHistorical={() => {
                    if (pinned) {
                        setPinned(false);
                    }
                }}
            />
        </div>
    );

    const heading = props.snapshot.title ?? props.snapshot.slug;

    return (
        <>
            <Head title={`${heading} — ${props.workbench.name}`} />

            {showHomeButton ? (
                <PreviewHomeButton isAuthenticated={isAuthenticated} />
            ) : null}

            {showBanner && banner.show ? (
                <NewRevisionBanner
                    renderedRevision={renderedRevision}
                    latestRevision={props.version.revision}
                    onView={() => {
                        banner.onView();
                    }}
                    onDismiss={banner.onDismiss}
                />
            ) : null}

            {showAppShell ? <AppLayout>{body}</AppLayout> : body}
        </>
    );
}

type BodyProps = {
    workbench: Workbench;
    snapshot: Snapshot;
    version: Version;
    versions: SnapshotVersionSummary[];
    showWorkbenchHeader: boolean;
    isFullscreen: boolean;
    onToggleFullscreen: () => void;
    fullBleed: boolean;
    isOwner: boolean;
    isPublicLink: boolean;
    visibility: SnapshotVisibility;
    shareUrl: string | null;
    shares: SnapshotShareSummary[];
    comments: CommentSummary[] | null;
    versionHistory: VersionHistoryEntry[] | null;
    isHistoricalView: boolean;
    showPinToggle: boolean;
    pinned: boolean;
    onTogglePinned: () => void;
    onSelectHistorical: () => void;
};

function SnapshotBody({
    workbench,
    snapshot,
    version,
    versions,
    showWorkbenchHeader,
    isFullscreen,
    onToggleFullscreen,
    fullBleed,
    isOwner,
    isPublicLink,
    visibility,
    shareUrl,
    shares,
    comments,
    versionHistory,
    isHistoricalView,
    showPinToggle,
    pinned,
    onTogglePinned,
    onSelectHistorical,
}: BodyProps) {
    const heading = snapshot.title ?? snapshot.slug;
    const subtitle = `${workbench.name} · revision ${version.revision}`;

    // REQ-M4-006: the version switcher is an owner-only affordance. Shared-with
    // viewers and public-link viewers always see the latest revision.
    const showVersionSwitcher = isOwner && !isPublicLink && versions.length > 0;

    if (!showWorkbenchHeader) {
        // Preview / fullscreen: render the view edge-to-edge with no chrome.
        return (
            <main data-testid="nexus-snapshot-body">
                {renderView(
                    version,
                    fullBleed,
                    snapshot.id,
                    comments,
                    versionHistory,
                    isHistoricalView,
                    workbench.slug,
                    snapshot.slug,
                )}
            </main>
        );
    }

    return (
        <div
            className={cn(
                'mx-auto flex w-full max-w-7xl flex-col gap-8 px-6 py-8',
            )}
        >
            <header
                className="flex flex-col gap-4"
                data-testid="nexus-workbench-header"
            >
                <Breadcrumb>
                    <BreadcrumbList>
                        <BreadcrumbItem>
                            <BreadcrumbLink asChild>
                                <Link href="/dashboard">Dashboard</Link>
                            </BreadcrumbLink>
                        </BreadcrumbItem>
                        <BreadcrumbSeparator />
                        <BreadcrumbItem>
                            <BreadcrumbLink asChild>
                                <Link href={`/workbenches/${workbench.slug}`}>
                                    {workbench.name}
                                </Link>
                            </BreadcrumbLink>
                        </BreadcrumbItem>
                        <BreadcrumbSeparator />
                        <BreadcrumbItem>
                            <BreadcrumbPage>{heading}</BreadcrumbPage>
                        </BreadcrumbItem>
                    </BreadcrumbList>
                </Breadcrumb>
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-3xl font-semibold tracking-tight">
                            {heading}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {subtitle}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {showPinToggle ? (
                            <PinToLatestToggle
                                pinned={pinned}
                                onToggle={onTogglePinned}
                            />
                        ) : null}
                        {showVersionSwitcher ? (
                            <VersionSwitcher
                                workbenchSlug={workbench.slug}
                                snapshotSlug={snapshot.slug}
                                versions={versions}
                                onSelectHistorical={onSelectHistorical}
                            />
                        ) : null}
                        {/* REQ-M4-010: owner-only Share control. Non-owners and
                            public-link viewers never see this button. */}
                        {isOwner && !isPublicLink ? (
                            <ShareDialog
                                workbenchSlug={workbench.slug}
                                snapshotSlug={snapshot.slug}
                                visibility={visibility}
                                shareUrl={shareUrl}
                                shares={shares}
                            />
                        ) : null}
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={onToggleFullscreen}
                            data-testid="nexus-fullscreen-toggle"
                            aria-pressed={isFullscreen}
                            aria-label={
                                isFullscreen
                                    ? 'Exit fullscreen'
                                    : 'Enter fullscreen'
                            }
                            title={
                                isFullscreen
                                    ? 'Exit Fullscreen (Esc)'
                                    : 'Enter Fullscreen'
                            }
                            className="inline-flex items-center gap-2"
                        >
                            {isFullscreen ? (
                                <Minimize2 className="size-4" aria-hidden />
                            ) : (
                                <Maximize2 className="size-4" aria-hidden />
                            )}
                            <span>
                                {isFullscreen
                                    ? 'Exit Fullscreen'
                                    : 'Fullscreen'}
                            </span>
                        </Button>
                    </div>
                </div>
            </header>

            <main data-testid="nexus-snapshot-body">
                {renderView(
                    version,
                    fullBleed,
                    snapshot.id,
                    comments,
                    versionHistory,
                    isHistoricalView,
                    workbench.slug,
                    snapshot.slug,
                )}
            </main>
        </div>
    );
}

function renderView(
    version: Version,
    fullBleed: boolean,
    snapshotId: number,
    comments: CommentSummary[] | null,
    versionHistory: VersionHistoryEntry[] | null,
    isHistoricalView: boolean,
    workbenchSlug: string,
    snapshotSlug: string,
) {
    if (version.view_type === 'table') {
        return (
            <TableView
                payload={version.data_payload as TableViewPayload}
                fullBleed={fullBleed}
            />
        );
    }

    if (version.view_type === 'slide_deck') {
        // SlideDeckView owns its own full-bleed presentation chrome via mode='presentation'.
        return (
            <SlideDeckView
                payload={version.data_payload as SlideDeckViewPayload}
                mode={fullBleed ? 'presentation' : 'embedded'}
            />
        );
    }

    if (version.view_type === 'kanban') {
        return (
            <KanbanView
                payload={version.data_payload as KanbanViewPayload}
                fullBleed={fullBleed}
            />
        );
    }

    if (version.view_type === 'flowchart') {
        return (
            <FlowchartView
                payload={version.data_payload as FlowchartViewPayload}
                fullBleed={fullBleed}
            />
        );
    }

    if (version.view_type === 'report') {
        // REQ-M5-008: the controller inlines resolved_blocks alongside the
        // raw data_payload so the React renderer doesn't fan out N HTTP
        // calls. The report payload type reflects both shapes.
        const reportPayload = {
            ...(version.data_payload as ReportViewPayload),
            resolved_blocks:
                (
                    version as {
                        resolved_blocks?: ReportViewPayload['resolved_blocks'];
                    }
                ).resolved_blocks ??
                (version.data_payload as ReportViewPayload).resolved_blocks,
        };

        return (
            <ReportView
                payload={reportPayload}
                fullBleed={fullBleed}
                snapshotId={snapshotId}
                comments={comments}
                versionHistory={versionHistory}
                isHistoricalView={isHistoricalView}
                workbenchSlug={workbenchSlug}
                snapshotSlug={snapshotSlug}
                activeRevision={version.revision}
            />
        );
    }

    return (
        <div className="rounded-lg border border-dashed border-border p-6 text-sm text-muted-foreground">
            Renderer for view_type{' '}
            <code className="font-mono">{version.view_type}</code> is not
            implemented yet.
        </div>
    );
}
