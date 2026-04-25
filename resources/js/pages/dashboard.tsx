import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Beaker, KeyRound } from 'lucide-react';
import { dashboard } from '@/routes';
import { edit as editTokens } from '@/routes/tokens';
import { agentActivity } from '@/routes/workbench';
import { show as showSnapshot } from '@/routes/workbench/snapshot';

type Zone = 'deck' | 'table' | 'kanban' | 'flow' | 'narrative';

type ViewType = 'slide_deck' | 'table' | 'kanban' | 'flowchart' | 'report' | string;

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

function EmptyState() {
    return (
        <div className="nx-card mx-auto max-w-[640px] p-8">
            <div className="flex items-center gap-2 font-mono text-[10.5px] uppercase tracking-[0.08em] text-[color:var(--fg-3)]">
                <Beaker size={13} />
                no workbenches yet
            </div>
            <h2 className="mt-3 font-serif text-[22px] leading-tight tracking-tight text-[color:var(--fg-0)]">
                A workbench is a project — a named home for the snapshots an
                agent produces.
            </h2>
            <p className="mt-3 font-mono text-[12px] leading-relaxed text-[color:var(--fg-2)]">
                Mint an MCP token, point an agent at the endpoint below, and the
                first call to <code className="rounded bg-[color:var(--bg-2)] px-1 text-[color:var(--fg-0)]">present_structured_data</code> creates a workbench you'll
                see here.
            </p>

            <div className="mt-5 rounded-[var(--radius-md)] border border-[color:var(--line)] bg-[color:var(--bg-2)] p-3">
                <div className="font-mono text-[10.5px] uppercase tracking-[0.08em] text-[color:var(--fg-3)]">
                    tool
                </div>
                <code className="mt-1 block truncate font-mono text-[12px] text-[color:var(--fg-0)]">
                    present_structured_data
                </code>
                <div className="mt-3 font-mono text-[10.5px] uppercase tracking-[0.08em] text-[color:var(--fg-3)]">
                    path
                </div>
                <code className="mt-1 block truncate font-mono text-[12px] text-[color:var(--fg-0)]">
                    POST /ai/mcp/nexus
                </code>
            </div>

            <Link
                href={editTokens()}
                prefetch
                className="nx-btn primary mt-5 justify-start"
            >
                <KeyRound size={13} />
                Mint MCP token
            </Link>
        </div>
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

            <div className="nx-stage-header">
                <div className="nx-stage-title">
                    <h1>
                        dashboard
                        <span className="nx-view-chip" data-zone="deck">
                            <span className="sq" />
                            library
                        </span>
                    </h1>
                    {!isEmpty && (
                        <div className="meta">
                            <span>
                                {workbenches.length === 1
                                    ? '1 workbench'
                                    : `${workbenches.length} workbenches`}
                            </span>
                            <span className="text-[color:var(--fg-4)]">·</span>
                            <span>
                                {recentSnapshots.length === 0
                                    ? 'no recent activity'
                                    : `${recentSnapshots.length} recent`}
                            </span>
                        </div>
                    )}
                </div>
            </div>

            <div className="nx-stage-body">
                <div className="mx-auto w-full max-w-[1120px] px-6 py-8">
                    {isEmpty ? (
                        <EmptyState />
                    ) : (
                        <>
                            {recentSnapshots.length > 0 && (
                                <section>
                                    <div className="flex items-baseline gap-3">
                                        <h2 className="font-serif text-[22px] leading-none tracking-tight text-[color:var(--fg-0)]">
                                            recents
                                        </h2>
                                        <span className="font-mono text-[11.5px] text-[color:var(--fg-3)]">
                                            jump back into your latest snapshots
                                        </span>
                                    </div>
                                    <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                                        {recentSnapshots.map((s) => {
                                            const zone = zoneForView(s.view_type);
                                            const href = showSnapshot({
                                                workbench: s.workbench_slug,
                                                snapshot: s.snapshot_slug,
                                            }).url;

                                            return (
                                                <Link
                                                    key={`${s.workbench_slug}/${s.snapshot_slug}/${s.revision}`}
                                                    href={href}
                                                    prefetch
                                                    className="nx-card group flex flex-col gap-2 p-3 hover:border-[color:var(--fg-3)]"
                                                >
                                                    <span
                                                        className="nx-view-chip self-start"
                                                        data-zone={zone}
                                                    >
                                                        <span className="sq" />
                                                        {zone}
                                                    </span>
                                                    <div className="min-w-0">
                                                        <div className="truncate text-[13px] text-[color:var(--fg-0)]">
                                                            {s.snapshot_title ??
                                                                s.snapshot_slug}
                                                        </div>
                                                        <div className="mt-0.5 truncate font-mono text-[11px] text-[color:var(--fg-3)]">
                                                            {s.workbench_name}
                                                        </div>
                                                    </div>
                                                    <div className="font-mono text-[11px] text-[color:var(--fg-3)]">
                                                        rev #
                                                        {String(
                                                            s.revision,
                                                        ).padStart(2, '0')}
                                                        {s.created_human
                                                            ? ` · ${s.created_human} ago`
                                                            : ''}
                                                    </div>
                                                </Link>
                                            );
                                        })}
                                    </div>
                                </section>
                            )}

                            <section className="mt-8">
                                <div className="flex items-baseline gap-3">
                                    <h2 className="font-serif text-[22px] leading-none tracking-tight text-[color:var(--fg-0)]">
                                        workbenches
                                    </h2>
                                    <span className="font-mono text-[11.5px] text-[color:var(--fg-3)]">
                                        sorted by last activity
                                    </span>
                                </div>
                                <ul className="nx-card mt-3 divide-y divide-[color:var(--line)] overflow-hidden">
                                    {workbenches.map((w) => (
                                        <li key={w.slug}>
                                            <Link
                                                href={workbenchHref(w)}
                                                prefetch
                                                className="flex items-center gap-3 px-4 py-3 hover:bg-[color:var(--bg-2)]"
                                            >
                                                <div className="min-w-0 flex-1">
                                                    <div className="truncate text-[13px] text-[color:var(--fg-0)]">
                                                        {w.name}
                                                    </div>
                                                    <div className="truncate font-mono text-[11px] text-[color:var(--fg-3)]">
                                                        {w.last_activity_human
                                                            ? `${w.last_activity_human} ago`
                                                            : 'no activity yet'}
                                                        {' · '}
                                                        {w.snapshot_count === 1
                                                            ? '1 snapshot'
                                                            : `${w.snapshot_count} snapshots`}
                                                    </div>
                                                </div>
                                                <ArrowRight
                                                    size={14}
                                                    className="text-[color:var(--fg-3)]"
                                                />
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        </>
                    )}
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'workbench',
            href: dashboard(),
        },
        {
            title: 'dashboard',
            href: dashboard(),
        },
    ],
};
