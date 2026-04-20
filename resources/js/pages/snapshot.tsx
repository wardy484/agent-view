import { Head } from '@inertiajs/react';
import { FlowchartView  } from '@/components/nexus/flowchart-view';
import type {FlowchartViewPayload} from '@/components/nexus/flowchart-view';
import { KanbanView  } from '@/components/nexus/kanban-view';
import type {KanbanViewPayload} from '@/components/nexus/kanban-view';
import { SlideDeckView  } from '@/components/nexus/slide-deck-view';
import type {SlideDeckViewPayload} from '@/components/nexus/slide-deck-view';
import { TableView  } from '@/components/nexus/table-view';
import type {TableViewPayload} from '@/components/nexus/table-view';
import { VersionSwitcher  } from '@/components/nexus/version-switcher';
import type {SnapshotVersionSummary} from '@/components/nexus/version-switcher';

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
};

type Props = {
    workbench: Workbench;
    snapshot: Snapshot;
    version: Version;
    versions: SnapshotVersionSummary[];
};

export default function SnapshotPage({ workbench, snapshot, version, versions }: Props) {
    const heading = snapshot.title ?? snapshot.slug;
    const subtitle = `${workbench.name} · revision ${version.revision}`;

    // Slide decks present full-bleed: no dashboard header, no sidebar chrome visible.
    // SlideDeckView renders `fixed inset-0` in presentation mode and covers AppLayout.
    if (version.view_type === 'slide_deck') {
        return (
            <>
                <Head title={`${heading} — ${workbench.name}`} />
                <SlideDeckView
                    payload={version.data_payload as SlideDeckViewPayload}
                    mode="presentation"
                />
            </>
        );
    }

    return (
        <>
            <Head title={`${heading} — ${workbench.name}`} />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-4 p-6">
                <header className="flex flex-col gap-2">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold tracking-tight">{heading}</h1>
                        <p className="text-sm text-muted-foreground">{subtitle}</p>
                    </div>
                    <VersionSwitcher
                        workbenchSlug={workbench.slug}
                        snapshotSlug={snapshot.slug}
                        versions={versions}
                    />
                </header>

                <main>{renderView(version)}</main>
            </div>
        </>
    );
}

function renderView(version: Version) {
    if (version.view_type === 'table') {
        return <TableView payload={version.data_payload as TableViewPayload} />;
    }

    if (version.view_type === 'kanban') {
        return <KanbanView payload={version.data_payload as KanbanViewPayload} />;
    }

    if (version.view_type === 'flowchart') {
        return <FlowchartView payload={version.data_payload as FlowchartViewPayload} />;
    }

    return (
        <div className="rounded-lg border border-dashed border-border p-6 text-sm text-muted-foreground">
            Renderer for view_type <code className="font-mono">{version.view_type}</code> is not implemented yet.
        </div>
    );
}
