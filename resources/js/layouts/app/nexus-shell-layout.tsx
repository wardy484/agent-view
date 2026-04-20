import { Link, usePage } from '@inertiajs/react';
import {
    Activity as ActivityIcon,
    Copy,
    KeyRound,
    Keyboard,
    LayoutGrid,
    Settings,
} from 'lucide-react';
import { type PropsWithChildren } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { UserMenuContent } from '@/components/user-menu-content';
import { dashboard } from '@/routes';
import { edit as editTokens } from '@/routes/tokens';
import type { BreadcrumbItem } from '@/types';

type NavEntry = {
    title: string;
    href: string;
    count?: number;
    active?: (path: string) => boolean;
};

const workbenches: NavEntry[] = [
    { title: 'incidents', href: '#', count: 12 },
    { title: 'release-gate', href: '#', count: 4 },
    { title: 'cost-audit', href: '#', count: 31 },
    { title: 'support-triage', href: '#', count: 8 },
];

export default function NexusShellLayout({
    children,
    breadcrumbs = [],
}: PropsWithChildren<{ breadcrumbs?: BreadcrumbItem[] }>) {
    const { url, props } = usePage();
    const user = props.auth?.user;

    const navItem = (
        entry: NavEntry,
        opts: { dot?: boolean; icon?: React.ReactNode } = {},
    ) => {
        const isActive = entry.href !== '#' && url.startsWith(entry.href);
        return (
            <Link
                key={entry.title + entry.href}
                href={entry.href}
                prefetch={entry.href !== '#' ? true : undefined}
                className={`nx-nav-item${isActive ? ' active' : ''}`}
            >
                {opts.icon ?? (opts.dot !== false && <span className="dot" />)}
                <span>{entry.title}</span>
                {entry.count !== undefined && (
                    <span className="count">{entry.count}</span>
                )}
            </Link>
        );
    };

    return (
        <div className="nx-app">
            {/* TOPBAR */}
            <header className="nx-topbar">
                <Link href={dashboard()} className="nx-brand">
                    <span
                        aria-hidden
                        className="inline-block size-[18px] rounded-[4px] bg-[color:var(--accent-brand)]"
                    />
                    <span>nexus</span>
                </Link>
                <nav className="nx-crumbs" aria-label="Breadcrumb">
                    {breadcrumbs.length === 0 ? (
                        <span className="current">workbench</span>
                    ) : (
                        breadcrumbs.map((crumb, i) => {
                            const last = i === breadcrumbs.length - 1;
                            return (
                                <span
                                    key={
                                        typeof crumb.href === 'string'
                                            ? crumb.href
                                            : crumb.title
                                    }
                                    className="flex items-center gap-2"
                                >
                                    {i > 0 && <span className="sep">/</span>}
                                    {last || !crumb.href ? (
                                        <span className="current">
                                            {crumb.title}
                                        </span>
                                    ) : (
                                        <Link href={crumb.href}>
                                            {crumb.title}
                                        </Link>
                                    )}
                                </span>
                            );
                        })
                    )}
                </nav>
                <div className="nx-topbar-right">
                    <span className="nx-pill live">live · mcp</span>
                    <span
                        aria-hidden
                        className="mx-1 h-4 w-px bg-[color:var(--line)]"
                    />
                    <button
                        type="button"
                        className="nx-icon-btn"
                        title="Copy permalink"
                        onClick={() =>
                            navigator.clipboard?.writeText(window.location.href)
                        }
                    >
                        <Copy size={13} />
                    </button>
                    <button
                        type="button"
                        className="nx-icon-btn"
                        title="Shortcuts"
                    >
                        <Keyboard size={13} />
                    </button>
                    {user && (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <button
                                    type="button"
                                    className="nx-icon-btn"
                                    title={user.name}
                                    style={{ width: 'auto', padding: '0 6px' }}
                                >
                                    <UserInfo user={user} />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                align="end"
                                className="w-56 rounded-lg"
                            >
                                <UserMenuContent user={user} />
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}
                </div>
            </header>

            {/* LEFT NAV */}
            <aside className="nx-nav" aria-label="Primary">
                <div className="nx-nav-section">workbenches</div>
                {workbenches.map((w) => navItem(w))}

                <div className="nx-nav-section">views</div>
                <span className="nx-nav-item">
                    <span
                        className="dot"
                        style={{ background: 'var(--zone-deck)' }}
                    />
                    <span>slide-deck</span>
                </span>
                <span className="nx-nav-item">
                    <span
                        className="dot"
                        style={{ background: 'var(--zone-table)' }}
                    />
                    <span>table</span>
                </span>
                <span className="nx-nav-item">
                    <span
                        className="dot"
                        style={{ background: 'var(--zone-kanban)' }}
                    />
                    <span>kanban</span>
                </span>
                <span className="nx-nav-item">
                    <span
                        className="dot"
                        style={{ background: 'var(--zone-flow)' }}
                    />
                    <span>flowchart</span>
                </span>

                <div className="nx-nav-section">system</div>
                {navItem(
                    { title: 'dashboard', href: dashboard().url },
                    { icon: <LayoutGrid size={12} /> },
                )}
                {navItem(
                    { title: 'api tokens', href: editTokens().url },
                    { icon: <KeyRound size={12} /> },
                )}
                {navItem(
                    { title: 'activity', href: '#' },
                    { icon: <ActivityIcon size={12} /> },
                )}
                {navItem(
                    { title: 'settings', href: '/settings/profile' },
                    { icon: <Settings size={12} /> },
                )}

                <div className="mt-auto flex items-center gap-2 border-t border-[color:var(--line)] px-1 pt-3 font-mono text-[11px] text-[color:var(--fg-3)]">
                    <span
                        aria-hidden
                        className="size-1.5 rounded-full bg-[color:var(--ok)] ring-[3px] ring-[color:oklch(0.76_0.15_150/0.2)]"
                    />
                    agent channel live
                </div>
            </aside>

            {/* STAGE */}
            <main className="nx-stage">{children}</main>
        </div>
    );
}
