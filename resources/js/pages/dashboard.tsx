import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Beaker, KeyRound } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';
import { edit as editTokens } from '@/routes/tokens';
import { agentActivity } from '@/routes/workbench';
import { show as showSnapshot } from '@/routes/workbench/snapshot';

type Zone = 'deck' | 'table' | 'kanban' | 'flow' | 'narrative';

type ViewType =
    | 'slide_deck'
    | 'table'
    | 'kanban'
    | 'flowchart'
    | 'report'
    | string;

type RecentSnapshot = {
    workbench_slug: string;
    workbench_name: string;
    snapshot_slug: string;
    snapshot_title: string | null;
    revision: number;
    view_type: ViewType;
    created_at: string | null;
    created_human: string | null;
};

type WorkbenchEntry = {
    slug: string;
    name: string;
    last_activity_at: string | null;
    last_activity_human: string | null;
    snapshot_count: number;
    latest_snapshot_slug: string | null;
};

type DashboardProps = {
    recentSnapshots: RecentSnapshot[];
    workbenches: WorkbenchEntry[];
};

const zoneForView = (view: ViewType): Zone => {
    switch (view) {
        case 'slide_deck':
            return 'deck';
        case 'kanban':
            return 'kanban';
        case 'flowchart':
            return 'flow';
        case 'report':
            return 'narrative';
        case 'table':
        default:
            return 'table';
    }
};

const workbenchHref = (w: WorkbenchEntry): string => {
    if (w.snapshot_count > 0 && w.latest_snapshot_slug) {
        return showSnapshot({
            workbench: w.slug,
            snapshot: w.latest_snapshot_slug,
        }).url;
    }

    return agentActivity({ workbench: w.slug }).url;
};

const SECTION_LABEL_CLASSES =
    'text-xs font-medium uppercase tracking-wide text-muted-foreground';

function EmptyState() {
    return (
        <Card className="mx-auto max-w-2xl">
            <CardHeader>
                <Badge variant="outline" className="gap-1.5">
                    <Beaker className="size-3" />
                    No Workbenches Yet
                </Badge>
                <CardTitle className="mt-2 text-2xl">
                    Workbenches Hold Your Snapshots
                </CardTitle>
                <CardDescription className="text-base">
                    A workbench is a project — a named home for the snapshots an
                    agent produces. Mint an MCP token, point an agent at the
                    endpoint below, and the first call to{' '}
                    <code className="rounded-md bg-muted px-1.5 py-0.5 font-mono text-sm">
                        present_structured_data
                    </code>{' '}
                    creates a workbench you'll see here.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 rounded-md border bg-muted/40 p-4">
                    <dt className={SECTION_LABEL_CLASSES}>Tool</dt>
                    <dd className="font-mono text-sm">
                        present_structured_data
                    </dd>
                    <dt className={SECTION_LABEL_CLASSES}>Path</dt>
                    <dd className="font-mono text-sm">POST /ai/mcp/nexus</dd>
                </dl>
                <Button asChild className="w-fit">
                    <Link href={editTokens()} prefetch>
                        <KeyRound className="size-4" />
                        Mint MCP Token
                    </Link>
                </Button>
            </CardContent>
        </Card>
    );
}

function SectionHeader({ title, helper }: { title: string; helper: string }) {
    return (
        <div className="flex items-baseline justify-between gap-3">
            <h2 className={SECTION_LABEL_CLASSES}>{title}</h2>
            <span className="text-xs text-muted-foreground">{helper}</span>
        </div>
    );
}

function RecentSnapshotCard({ snapshot }: { snapshot: RecentSnapshot }) {
    const zone = zoneForView(snapshot.view_type);
    const href = showSnapshot({
        workbench: snapshot.workbench_slug,
        snapshot: snapshot.snapshot_slug,
    }).url;

    return (
        <Link
            href={href}
            prefetch
            className="group block rounded-lg outline-none focus-visible:ring-2 focus-visible:ring-ring"
        >
            <Card className="h-full gap-3 py-4 transition-colors group-hover:border-foreground/30">
                <CardHeader className="px-4">
                    <Badge variant="outline" className="w-fit capitalize">
                        {zone}
                    </Badge>
                </CardHeader>
                <CardContent className="px-4">
                    <div className="truncate text-sm font-medium text-foreground">
                        {snapshot.snapshot_title ?? snapshot.snapshot_slug}
                    </div>
                    <div className="mt-0.5 truncate text-xs text-muted-foreground">
                        {snapshot.workbench_name}
                    </div>
                    <div className="mt-2 font-mono text-xs text-muted-foreground">
                        rev #{String(snapshot.revision).padStart(2, '0')}
                        {snapshot.created_human
                            ? ` · ${snapshot.created_human} ago`
                            : ''}
                    </div>
                </CardContent>
            </Card>
        </Link>
    );
}

export default function Dashboard({
    recentSnapshots,
    workbenches,
}: DashboardProps) {
    const isEmpty = workbenches.length === 0;

    return (
        <>
            <Head title="Dashboard" />

            <div className="border-b bg-background">
                <div className="mx-auto flex w-full max-w-6xl flex-col gap-2 px-6 py-6">
                    <div className="flex items-center gap-3">
                        <h1 className="text-3xl font-semibold tracking-tight">
                            Dashboard
                        </h1>
                        <Badge variant="secondary">Library</Badge>
                    </div>
                    {!isEmpty && (
                        <p className="text-sm text-muted-foreground">
                            {workbenches.length === 1
                                ? '1 workbench'
                                : `${workbenches.length} workbenches`}
                            {' · '}
                            {recentSnapshots.length === 0
                                ? 'no recent activity'
                                : `${recentSnapshots.length} recent`}
                        </p>
                    )}
                </div>
            </div>

            <div className="mx-auto w-full max-w-6xl px-6 py-8">
                {isEmpty ? (
                    <EmptyState />
                ) : (
                    <div className="flex flex-col gap-8">
                        {recentSnapshots.length > 0 && (
                            <section className="flex flex-col gap-4">
                                <SectionHeader
                                    title="Recents"
                                    helper="jump back into your latest snapshots"
                                />
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                                    {recentSnapshots.map((s) => (
                                        <RecentSnapshotCard
                                            key={`${s.workbench_slug}/${s.snapshot_slug}/${s.revision}`}
                                            snapshot={s}
                                        />
                                    ))}
                                </div>
                            </section>
                        )}

                        <section className="flex flex-col gap-4">
                            <SectionHeader
                                title="Workbenches"
                                helper="sorted by last activity"
                            />
                            <Card className="gap-0 overflow-hidden p-0">
                                <ul className="divide-y">
                                    {workbenches.map((w) => (
                                        <li key={w.slug}>
                                            <Link
                                                href={workbenchHref(w)}
                                                prefetch
                                                className="flex items-center gap-3 px-4 py-3 text-sm transition-colors hover:bg-muted focus-visible:bg-muted focus-visible:outline-none"
                                            >
                                                <div className="min-w-0 flex-1">
                                                    <div className="truncate font-medium text-foreground">
                                                        {w.name}
                                                    </div>
                                                    <div className="mt-0.5 truncate text-xs text-muted-foreground">
                                                        {w.last_activity_human
                                                            ? `${w.last_activity_human} ago`
                                                            : 'no activity yet'}
                                                        {' · '}
                                                        {w.snapshot_count === 1
                                                            ? '1 snapshot'
                                                            : `${w.snapshot_count} snapshots`}
                                                    </div>
                                                </div>
                                                <ArrowRight className="size-4 shrink-0 text-muted-foreground" />
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </Card>
                        </section>
                    </div>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Workbench',
            href: dashboard(),
        },
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
