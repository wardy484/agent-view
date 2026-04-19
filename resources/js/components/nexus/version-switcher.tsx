import { Link } from '@inertiajs/react';

import { cn } from '@/lib/utils';

export type SnapshotVersionSummary = {
    id: number;
    revision: number;
    view_type: string;
    created_at: string | null;
    is_current: boolean;
};

type Props = {
    workbenchSlug: string;
    snapshotSlug: string;
    versions: SnapshotVersionSummary[];
    className?: string;
};

/**
 * REQ-M1-007: lists every revision of a snapshot newest first and navigates
 * to the selected revision via `?revision=N`.
 */
export function VersionSwitcher({ workbenchSlug, snapshotSlug, versions, className }: Props) {
    if (versions.length <= 1) {
        return null;
    }

    return (
        <nav
            data-testid="nexus-version-switcher"
            aria-label="Snapshot revisions"
            className={cn('flex flex-wrap items-center gap-1 rounded-lg border border-border bg-background p-1', className)}
        >
            <span className="px-2 text-xs uppercase tracking-wide text-muted-foreground">Revisions</span>
            {versions.map((version) => (
                <Link
                    key={version.id}
                    href={buildHref(workbenchSlug, snapshotSlug, version.revision)}
                    preserveScroll
                    className={cn(
                        'rounded-md px-2 py-1 text-xs font-medium',
                        version.is_current
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                    )}
                    aria-current={version.is_current ? 'true' : undefined}
                >
                    r{version.revision}
                </Link>
            ))}
        </nav>
    );
}

function buildHref(workbenchSlug: string, snapshotSlug: string, revision: number): string {
    const url = new URL(`/workbenches/${workbenchSlug}/snapshots/${snapshotSlug}`, window.location.origin);
    url.searchParams.set('revision', String(revision));

    return `${url.pathname}${url.search}`;
}
