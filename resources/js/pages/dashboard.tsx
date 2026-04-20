import { Head, Link } from '@inertiajs/react';
import {
    Activity,
    ArrowRight,
    Columns3,
    KeyRound,
    LayoutList,
    Network,
    Presentation,
    Share2,
} from 'lucide-react';
import { dashboard } from '@/routes';
import { edit as editTokens } from '@/routes/tokens';

type Zone = 'deck' | 'table' | 'kanban' | 'flow';

const viewTypes: {
    zone: Zone;
    title: string;
    subtitle: string;
    icon: React.ReactNode;
}[] = [
    {
        zone: 'deck',
        title: 'Slide deck',
        subtitle: '16:9 narrative — lede, bullets, timeline',
        icon: <Presentation size={16} />,
    },
    {
        zone: 'table',
        title: 'Table',
        subtitle: 'tabular rows · filter · select · send-back',
        icon: <LayoutList size={16} />,
    },
    {
        zone: 'kanban',
        title: 'Kanban',
        subtitle: 'status columns · drag · lane totals',
        icon: <Columns3 size={16} />,
    },
    {
        zone: 'flow',
        title: 'Flowchart',
        subtitle: 'nodes · edges · highlights',
        icon: <Network size={16} />,
    },
];

const recentSnapshots = [
    {
        workbench: 'incidents',
        snapshot: '2024-apr-19-pager',
        rev: 7,
        view: 'deck' as Zone,
        when: '12m ago',
        by: 'claude-sonnet-4.5',
    },
    {
        workbench: 'release-gate',
        snapshot: 'v2.14.0-canary',
        rev: 3,
        view: 'table' as Zone,
        when: '44m ago',
        by: 'cursor-agent',
    },
    {
        workbench: 'cost-audit',
        snapshot: 'q2-overruns',
        rev: 12,
        view: 'kanban' as Zone,
        when: '2h ago',
        by: 'claude-opus-4',
    },
];

export default function Dashboard() {
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
                        <span>workbench · {recentSnapshots.length} active</span>
                        <span className="text-[color:var(--fg-4)]">·</span>
                        <span>mcp endpoint ready</span>
                    </div>
                </div>
                <div className="nx-stage-actions">
                    <button type="button" className="nx-btn ghost">
                        <Share2 size={13} />
                        Share
                    </button>
                    <Link href={editTokens()} className="nx-btn primary">
                        <KeyRound size={13} />
                        Mint MCP token
                    </Link>
                </div>
            </div>

            <div className="nx-stage-body">
                <div className="mx-auto w-full max-w-[1120px] px-6 py-8">
                    <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                        {[
                            {
                                label: 'workbenches',
                                value: '4',
                                hint: '12 active snapshots',
                            },
                            {
                                label: 'revisions today',
                                value: '27',
                                hint: '+6 vs yesterday',
                            },
                            {
                                label: 'selections sent back',
                                value: '9',
                                hint: 'avg 3 per snapshot',
                            },
                            {
                                label: 'mcp tool calls',
                                value: '1.4k',
                                hint: 'p95 240ms',
                            },
                        ].map((kpi) => (
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
                        {viewTypes.map((v) => (
                            <div
                                key={v.zone}
                                className="nx-card group relative flex flex-col gap-3 p-4"
                            >
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
                            </div>
                        ))}
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
                                <Link
                                    href="#"
                                    className="font-mono text-[11.5px] text-[color:var(--fg-2)] hover:text-[color:var(--fg-0)]"
                                >
                                    view all →
                                </Link>
                            </div>
                            <ul className="divide-y divide-[color:var(--line)]">
                                {recentSnapshots.map((s) => (
                                    <li
                                        key={s.snapshot}
                                        className="flex items-center gap-3 px-4 py-3 hover:bg-[color:var(--bg-2)]"
                                    >
                                        <span
                                            className="nx-view-chip"
                                            data-zone={s.view}
                                        >
                                            <span className="sq" />
                                            {s.view}
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <div className="truncate text-[13px] text-[color:var(--fg-0)]">
                                                {s.workbench}{' '}
                                                <span className="text-[color:var(--fg-4)]">
                                                    /
                                                </span>{' '}
                                                <span className="font-mono text-[12px]">
                                                    {s.snapshot}
                                                </span>
                                            </div>
                                            <div className="truncate font-mono text-[11px] text-[color:var(--fg-3)]">
                                                rev #
                                                {String(s.rev).padStart(2, '0')}{' '}
                                                · {s.when} · {s.by}
                                            </div>
                                        </div>
                                        <ArrowRight
                                            size={14}
                                            className="text-[color:var(--fg-3)]"
                                        />
                                    </li>
                                ))}
                            </ul>
                        </div>

                        <div className="nx-card flex flex-col gap-3 p-4">
                            <div className="font-mono text-[10.5px] uppercase tracking-[0.08em] text-[color:var(--fg-3)]">
                                quick actions
                            </div>
                            <Link
                                href={editTokens()}
                                className="nx-btn primary justify-start"
                            >
                                <KeyRound size={13} />
                                Mint MCP token
                            </Link>
                            <button type="button" className="nx-btn">
                                <Presentation size={13} />
                                New workbench
                            </button>
                            <button type="button" className="nx-btn ghost">
                                <Share2 size={13} />
                                Invite collaborator
                            </button>
                            <div className="mt-auto rounded-[var(--radius-md)] border border-[color:var(--line)] bg-[color:var(--bg-2)] p-3">
                                <div className="font-mono text-[10.5px] uppercase tracking-[0.08em] text-[color:var(--fg-3)]">
                                    endpoint
                                </div>
                                <code className="mt-1 block truncate font-mono text-[12px] text-[color:var(--fg-0)]">
                                    POST /mcp/present_structured_data
                                </code>
                            </div>
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
