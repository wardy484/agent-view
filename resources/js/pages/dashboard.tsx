import { Head, Link } from '@inertiajs/react';
import {
    Activity,
    ArrowRight,
    Columns3,
    FileText,
    KeyRound,
    LayoutList,
    Network,
    Presentation,
} from 'lucide-react';
import { dashboard } from '@/routes';
import { edit as editTokens } from '@/routes/tokens';
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

type ViewTypeSample = {
    workbench_slug: string;
    snapshot_slug: string;
    snapshot_title: string | null;
} | null;

type ViewTypeSamples = {
    slide_deck: ViewTypeSample;
    table: ViewTypeSample;
    kanban: ViewTypeSample;
    flowchart: ViewTypeSample;
    // REQ-M5-000: narrative view_type that embeds other snapshots inline.
    report: ViewTypeSample;
};

type DashboardProps = {
    kpis: {
        workbenches: number;
        snapshots: number;
        revisions_today: number;
        mcp_calls_today: number;
    };
    recentSnapshots: RecentSnapshot[];
    viewTypeSamples: ViewTypeSamples;
};

const viewTypes: {
    key: keyof ViewTypeSamples;
    zone: Zone;
    title: string;
    subtitle: string;
    icon: React.ReactNode;
}[] = [
    {
        key: 'slide_deck',
        zone: 'deck',
        title: 'Slide deck',
        subtitle: '16:9 narrative — lede, bullets, timeline',
        icon: <Presentation size={16} />,
    },
    {
        key: 'table',
        zone: 'table',
        title: 'Table',
        subtitle: 'tabular rows · filter · select · send-back',
        icon: <LayoutList size={16} />,
    },
    {
        key: 'kanban',
        zone: 'kanban',
        title: 'Kanban',
        subtitle: 'status columns · drag · lane totals',
        icon: <Columns3 size={16} />,
    },
    {
        key: 'flowchart',
        zone: 'flow',
        title: 'Flowchart',
        subtitle: 'nodes · edges · highlights',
        icon: <Network size={16} />,
    },
    {
        // REQ-M5-000: narrative bundle of markdown prose + embedded snapshots.
        key: 'report',
        zone: 'narrative',
        title: 'Report',
        subtitle: 'markdown prose · embedded snapshots · pinned revisions',
        icon: <FileText size={16} />,
    },
];

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

const formatNumber = (n: number): string => {
    if (n >= 1000) {
        return `${(n / 1000).toFixed(n >= 10000 ? 0 : 1)}k`;
    }

    return n.toString();
};

export default function Dashboard({
    kpis,
    recentSnapshots,
    viewTypeSamples,
}: DashboardProps) {
    const kpiCards = [
        {
            label: 'workbenches',
            value: formatNumber(kpis.workbenches),
            hint:
                kpis.snapshots === 1
                    ? '1 snapshot total'
                    : `${formatNumber(kpis.snapshots)} snapshots total`,
        },
        {
            label: 'revisions today',
            value: formatNumber(kpis.revisions_today),
            hint: kpis.revisions_today === 0 ? 'none yet today' : 'since midnight',
        },
        {
            label: 'snapshots',
            value: formatNumber(kpis.snapshots),
            hint: 'all-time across workbenches',
        },
        {
            label: 'mcp tool calls',
            value: formatNumber(kpis.mcp_calls_today),
            hint: kpis.mcp_calls_today === 0 ? 'awaiting agent traffic' : 'today',
        },
    ];

    return (
        <>
            <Head title="Dashboard" />

            <div className="nx-stage-header">
                <div className="nx-stage-title">
                    <h1>
                        dashboard
                        <span className="nx-view-chip" data-zone="deck">
                            <span className="sq" />
                            overview
                        </span>
                    </h1>
                    <div className="meta">
                        <span>
                            {kpis.workbenches === 1
                                ? '1 workbench'
                                : `${formatNumber(kpis.workbenches)} workbenches`}
                        </span>
                        <span className="text-[color:var(--fg-4)]">·</span>
                        <span>
                            {kpis.snapshots === 1
                                ? '1 snapshot'
                                : `${formatNumber(kpis.snapshots)} snapshots`}
                        </span>
                    </div>
                </div>
                <div className="nx-stage-actions">
                    <Link href={editTokens()} className="nx-btn primary">
                        <KeyRound size={13} />
                        Mint MCP token
                    </Link>
                </div>
            </div>

            <div className="nx-stage-body">
                <div className="mx-auto w-full max-w-[1120px] px-6 py-8">
                    <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                        {kpiCards.map((kpi) => (
                            <div key={kpi.label} className="nx-card p-4">
                                <div className="font-mono text-[10.5px] uppercase tracking-[0.08em] text-[color:var(--fg-3)]">
                                    {kpi.label}
                                </div>
                                <div className="mt-2 text-3xl font-semibold tracking-tight text-[color:var(--fg-0)]">
                                    {kpi.value}
                                </div>
                                <div className="mt-1 font-mono text-[11.5px] text-[color:var(--fg-3)]">
                                    {kpi.hint}
                                </div>
                            </div>
                        ))}
                    </div>

                    <div className="mt-8 flex items-baseline gap-3">
                        <h2 className="font-serif text-[22px] leading-none tracking-tight text-[color:var(--fg-0)]">
                            view types
                        </h2>
                        <span className="font-mono text-[11.5px] text-[color:var(--fg-3)]">
                            present_structured_data supports 4 schemas
                        </span>
                    </div>
                    <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        {viewTypes.map((v) => {
                            const sample = viewTypeSamples[v.key];
                            const cardInner = (
                                <>
                                    <div className="flex items-center justify-between">
                                        <span
                                            className="nx-view-chip"
                                            data-zone={v.zone}
                                        >
                                            <span className="sq" />
                                            {v.zone}
                                        </span>
                                        <span className="text-[color:var(--fg-3)] transition-colors group-hover:text-[color:var(--fg-0)]">
                                            {v.icon}
                                        </span>
                                    </div>
                                    <div>
                                        <div className="text-[14px] font-semibold text-[color:var(--fg-0)]">
                                            {v.title}
                                        </div>
                                        <div className="mt-0.5 font-mono text-[11.5px] leading-snug text-[color:var(--fg-2)]">
                                            {v.subtitle}
                                        </div>
                                    </div>
                                    <div className="font-mono text-[11px] text-[color:var(--fg-3)]">
                                        {sample ? (
                                            <span className="inline-flex items-center gap-1 text-[color:var(--fg-2)]">
                                                open sample
                                                <ArrowRight size={11} />
                                            </span>
                                        ) : (
                                            <span>no samples yet</span>
                                        )}
                                    </div>
                                </>
                            );

                            if (sample) {
                                return (
                                    <Link
                                        key={v.key}
                                        href={
                                            showSnapshot({
                                                workbench:
                                                    sample.workbench_slug,
                                                snapshot: sample.snapshot_slug,
                                            }).url
                                        }
                                        prefetch
                                        className="nx-card group relative flex flex-col gap-3 p-4 hover:border-[color:var(--fg-3)]"
                                    >
                                        {cardInner}
                                    </Link>
                                );
                            }

                            return (
                                <div
                                    key={v.key}
                                    className="nx-card group relative flex flex-col gap-3 p-4 opacity-75"
                                >
                                    {cardInner}
                                </div>
                            );
                        })}
                    </div>

                    <div className="mt-8 grid grid-cols-1 gap-3 lg:grid-cols-3">
                        <div className="nx-card overflow-hidden lg:col-span-2">
                            <div className="flex items-center justify-between border-b border-[color:var(--line)] px-4 py-3">
                                <div className="flex items-center gap-2">
                                    <Activity
                                        size={13}
                                        className="text-[color:var(--fg-2)]"
                                    />
                                    <span className="font-mono text-[11.5px] uppercase tracking-[0.08em] text-[color:var(--fg-3)]">
                                        recent snapshots
                                    </span>
                                </div>
                                <span className="font-mono text-[11.5px] text-[color:var(--fg-3)]">
                                    {recentSnapshots.length} shown
                                </span>
                            </div>
                            {recentSnapshots.length === 0 ? (
                                <div className="px-4 py-10 text-center font-mono text-[12px] text-[color:var(--fg-3)]">
                                    No snapshots yet. When an agent calls
                                    <code className="mx-1 rounded bg-[color:var(--bg-2)] px-1 text-[color:var(--fg-0)]">
                                        present_structured_data
                                    </code>
                                    they'll appear here.
                                </div>
                            ) : (
                                <ul className="divide-y divide-[color:var(--line)]">
                                    {recentSnapshots.map((s) => {
                                        const zone = zoneForView(s.view_type);
                                        const href = showSnapshot({
                                            workbench: s.workbench_slug,
                                            snapshot: s.snapshot_slug,
                                        }).url;

                                        return (
                                            <li
                                                key={`${s.workbench_slug}/${s.snapshot_slug}/${s.revision}`}
                                            >
                                                <Link
                                                    href={href}
                                                    prefetch
                                                    className="flex items-center gap-3 px-4 py-3 hover:bg-[color:var(--bg-2)]"
                                                >
                                                    <span
                                                        className="nx-view-chip"
                                                        data-zone={zone}
                                                    >
                                                        <span className="sq" />
                                                        {zone}
                                                    </span>
                                                    <div className="min-w-0 flex-1">
                                                        <div className="truncate text-[13px] text-[color:var(--fg-0)]">
                                                            {s.workbench_name}{' '}
                                                            <span className="text-[color:var(--fg-4)]">
                                                                /
                                                            </span>{' '}
                                                            <span className="font-mono text-[12px]">
                                                                {s.snapshot_title ??
                                                                    s.snapshot_slug}
                                                            </span>
                                                        </div>
                                                        <div className="truncate font-mono text-[11px] text-[color:var(--fg-3)]">
                                                            rev #
                                                            {String(
                                                                s.revision,
                                                            ).padStart(2, '0')}
                                                            {s.created_human
                                                                ? ` · ${s.created_human} ago`
                                                                : ''}
                                                        </div>
                                                    </div>
                                                    <ArrowRight
                                                        size={14}
                                                        className="text-[color:var(--fg-3)]"
                                                    />
                                                </Link>
                                            </li>
                                        );
                                    })}
                                </ul>
                            )}
                        </div>

                        <div className="nx-card flex flex-col gap-3 p-4">
                            <div className="font-mono text-[10.5px] uppercase tracking-[0.08em] text-[color:var(--fg-3)]">
                                mcp endpoint
                            </div>
                            <div className="rounded-[var(--radius-md)] border border-[color:var(--line)] bg-[color:var(--bg-2)] p-3">
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
                                className="nx-btn primary justify-start"
                            >
                                <KeyRound size={13} />
                                Manage API tokens
                            </Link>
                            <p className="font-mono text-[11px] leading-snug text-[color:var(--fg-3)]">
                                Scope each Sanctum token to a workbench slug
                                with the
                                <code className="mx-1 rounded bg-[color:var(--bg-2)] px-1 text-[color:var(--fg-0)]">
                                    workbench:&lt;slug&gt;
                                </code>
                                ability.
                            </p>
                        </div>
                    </div>
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
