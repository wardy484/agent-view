import { Head } from '@inertiajs/react';
import { Maximize2, Minimize2 } from 'lucide-react';
import { useEffect, useState } from 'react';

import { FlowchartView } from '@/components/nexus/flowchart-view';
import type { FlowchartViewPayload } from '@/components/nexus/flowchart-view';
import { KanbanView } from '@/components/nexus/kanban-view';
import type { KanbanViewPayload } from '@/components/nexus/kanban-view';
import { PreviewHomeButton } from '@/components/nexus/preview-home-button';
import { ReportView } from '@/components/nexus/report-view';
import type { ReportViewPayload } from '@/components/nexus/report-view';
import { SlideDeckView } from '@/components/nexus/slide-deck-view';
import type { SlideDeckViewPayload } from '@/components/nexus/slide-deck-view';
import { TableView } from '@/components/nexus/table-view';
import type { TableViewPayload } from '@/components/nexus/table-view';
import { VersionSwitcher } from '@/components/nexus/version-switcher';
import type { SnapshotVersionSummary } from '@/components/nexus/version-switcher';
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
    const { mode, isAuthenticated, is_owner = true, is_public_link = false } = props;
    const isPreview = mode === 'preview';
    const [isFullscreen, setIsFullscreen] = useState(false);

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

    const body = (
        <SnapshotBody
            workbench={props.workbench}
            snapshot={props.snapshot}
            version={props.version}
            versions={props.versions}
            showWorkbenchHeader={showWorkbenchHeader}
            isFullscreen={isFullscreen}
            onToggleFullscreen={() => setIsFullscreen((current) => !current)}
            fullBleed={fullBleed}
            isOwner={is_owner}
            isPublicLink={is_public_link}
        />
    );

    const heading = props.snapshot.title ?? props.snapshot.slug;

    return (
        <>
            <Head title={`${heading} — ${props.workbench.name}`} />

            {showHomeButton ? <PreviewHomeButton isAuthenticated={isAuthenticated} /> : null}

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
}: BodyProps) {
    const heading = snapshot.title ?? snapshot.slug;
    const subtitle = `${workbench.name} · revision ${version.revision}`;

    // REQ-M4-006: the version switcher is an owner-only affordance. Shared-with
    // viewers and public-link viewers always see the latest revision.
    const showVersionSwitcher = isOwner && !isPublicLink && versions.length > 0;

    if (!showWorkbenchHeader) {
        // Preview / fullscreen: render the view edge-to-edge with no chrome.
        return <main data-testid="nexus-snapshot-body">{renderView(version, fullBleed)}</main>;
    }

    return (
        <div className={cn('mx-auto flex w-full max-w-6xl flex-col gap-4 p-6')}>
            <header className="flex flex-col gap-2" data-testid="nexus-workbench-header">
                <div className="flex items-start justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold tracking-tight">{heading}</h1>
                        <p className="text-sm text-muted-foreground">{subtitle}</p>
                    </div>
                    <button
                        type="button"
                        onClick={onToggleFullscreen}
                        data-testid="nexus-fullscreen-toggle"
                        aria-pressed={isFullscreen}
                        title={isFullscreen ? 'Exit fullscreen (Esc)' : 'Enter fullscreen'}
                        className="inline-flex h-9 items-center gap-2 rounded-md border border-border bg-background px-3 text-xs font-medium text-muted-foreground shadow-sm hover:text-foreground"
                    >
                        {isFullscreen ? (
                            <Minimize2 className="size-4" aria-hidden />
                        ) : (
                            <Maximize2 className="size-4" aria-hidden />
                        )}
                        <span>{isFullscreen ? 'Exit fullscreen' : 'Fullscreen'}</span>
                    </button>
                </div>
                {showVersionSwitcher ? (
                    <VersionSwitcher
                        workbenchSlug={workbench.slug}
                        snapshotSlug={snapshot.slug}
                        versions={versions}
                    />
                ) : null}
            </header>

            <main data-testid="nexus-snapshot-body">{renderView(version, fullBleed)}</main>
        </div>
    );
}

function renderView(version: Version, fullBleed: boolean) {
    if (version.view_type === 'table') {
        return <TableView payload={version.data_payload as TableViewPayload} fullBleed={fullBleed} />;
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
        return <KanbanView payload={version.data_payload as KanbanViewPayload} fullBleed={fullBleed} />;
    }

    if (version.view_type === 'flowchart') {
        return <FlowchartView payload={version.data_payload as FlowchartViewPayload} fullBleed={fullBleed} />;
    }

    if (version.view_type === 'report') {
        // REQ-M5-008: the controller inlines resolved_blocks alongside the
        // raw data_payload so the React renderer doesn't fan out N HTTP
        // calls. The report payload type reflects both shapes.
        const reportPayload = {
            ...(version.data_payload as ReportViewPayload),
            resolved_blocks:
                (version as { resolved_blocks?: ReportViewPayload['resolved_blocks'] }).resolved_blocks ??
                (version.data_payload as ReportViewPayload).resolved_blocks,
        };

        return <ReportView payload={reportPayload} fullBleed={fullBleed} />;
    }

    return (
        <div className="rounded-lg border border-dashed border-border p-6 text-sm text-muted-foreground">
            Renderer for view_type <code className="font-mono">{version.view_type}</code> is not implemented yet.
        </div>
    );
}
