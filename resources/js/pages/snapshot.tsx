import { Head } from '@inertiajs/react';
import { TableView, type TableViewPayload } from '@/components/nexus/table-view';

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
};

export default function SnapshotPage({ workbench, snapshot, version }: Props) {
    const heading = snapshot.title ?? snapshot.slug;
    const subtitle = `${workbench.name} · revision ${version.revision}`;

    return (
        <>
            <Head title={`${heading} — ${workbench.name}`} />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-4 p-6">
                <header className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold tracking-tight">{heading}</h1>
                    <p className="text-sm text-muted-foreground">{subtitle}</p>
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

    return (
        <div className="rounded-lg border border-dashed border-border p-6 text-sm text-muted-foreground">
            Renderer for view_type <code className="font-mono">{version.view_type}</code> is not implemented yet.
        </div>
    );
}
