import { Link, usePage } from '@inertiajs/react';
import { Copy, KeyRound, LayoutGrid, Settings } from 'lucide-react';
import { type PropsWithChildren } from 'react';
import {
    Avatar,
    AvatarFallback,
    AvatarImage,
} from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import { dashboard } from '@/routes';
import { edit as editTokens } from '@/routes/tokens';
import { show as showSnapshot } from '@/routes/workbench/snapshot';
import type { BreadcrumbItem, SharedWorkbench } from '@/types';

export default function NexusShellLayout({
    children,
    breadcrumbs = [],
}: PropsWithChildren<{ breadcrumbs?: BreadcrumbItem[] }>) {
    const { url, props } = usePage();
    const user = props.auth?.user;
    const workbenches = (props.workbenches ?? []) as SharedWorkbench[];
    const getInitials = useInitials();

    const isActive = (href: string) => href !== '#' && url.startsWith(href);

    const systemItems = [
        {
            title: 'dashboard',
            href: dashboard().url,
            icon: <LayoutGrid size={12} />,
        },
        {
            title: 'api tokens',
            href: editTokens().url,
            icon: <KeyRound size={12} />,
        },
        {
            title: 'settings',
            href: '/settings/profile',
            icon: <Settings size={12} />,
        },
    ];

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
                    <span className="nx-pill live">mcp ready</span>
                    <span
                        aria-hidden
                        className="mx-1 h-4 w-px bg-[color:var(--line)]"
                    />
                    <button
                        type="button"
                        className="nx-icon-btn"
                        title="Copy permalink"
                        onClick={() =>
                            navigator.clipboard?.writeText(
                                window.location.href,
                            )
                        }
                    >
                        <Copy size={13} />
                    </button>
                    {user && (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <button
                                    type="button"
                                    className="nx-icon-btn flex items-center gap-2 !w-auto !h-7 !px-1.5"
                                    title={user.name}
                                >
                                    <Avatar className="size-5 rounded-full">
                                        <AvatarImage
                                            src={user.avatar}
                                            alt={user.name}
                                        />
                                        <AvatarFallback className="rounded-full bg-[color:var(--bg-2)] text-[10px] font-medium text-[color:var(--fg-1)]">
                                            {getInitials(user.name)}
                                        </AvatarFallback>
                                    </Avatar>
                                    <span className="max-w-[140px] truncate text-[12px] font-medium text-[color:var(--fg-1)]">
                                        {user.name}
                                    </span>
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
                {workbenches.length === 0 ? (
                    <div className="nx-nav-empty">
                        No workbenches yet. Agents create them on first
                        snapshot.
                    </div>
                ) : (
                    workbenches.map((w) => {
                        const href = w.latest_snapshot_slug
                            ? showSnapshot({
                                  workbench: w.slug,
                                  snapshot: w.latest_snapshot_slug,
                              }).url
                            : '#';
                        const active = isActive(
                            `/workbenches/${w.slug}/`,
                        );
                        if (href === '#') {
                            return (
                                <span
                                    key={w.slug}
                                    className={`nx-nav-item${active ? ' active' : ''}`}
                                    title={`${w.name} · no snapshots yet`}
                                >
                                    <span className="dot" />
                                    <span>{w.name}</span>
                                    <span className="count">
                                        {w.snapshot_count}
                                    </span>
                                </span>
                            );
                        }
                        return (
                            <Link
                                key={w.slug}
                                href={href}
                                prefetch
                                className={`nx-nav-item${active ? ' active' : ''}`}
                            >
                                <span className="dot" />
                                <span>{w.name}</span>
                                <span className="count">
                                    {w.snapshot_count}
                                </span>
                            </Link>
                        );
                    })
                )}

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
                {systemItems.map((item) => (
                    <Link
                        key={item.href}
                        href={item.href}
                        prefetch
                        className={`nx-nav-item${isActive(item.href) ? ' active' : ''}`}
                    >
                        {item.icon}
                        <span>{item.title}</span>
                    </Link>
                ))}

                <div className="mt-auto flex items-center gap-2 border-t border-[color:var(--line)] px-1 pt-3 font-mono text-[11px] text-[color:var(--fg-3)]">
                    <span
                        aria-hidden
                        className="size-1.5 rounded-full bg-[color:var(--ok)] ring-[3px] ring-[color:oklch(0.76_0.15_150/0.2)]"
                    />
                    mcp endpoint online
                </div>
            </aside>

            {/* STAGE */}
            <main className="nx-stage">{children}</main>
        </div>
    );
}
