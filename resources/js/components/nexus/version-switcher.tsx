import { Link } from '@inertiajs/react';
import { Check, History } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
    // When the user explicitly clicks a non-current revision, the snapshot
    // page disables "pin to latest" so live updates don't yank them back.
    onSelectHistorical?: () => void;
};

/**
 * REQ-M1-007 / REQ-M9-009: lists every revision of a snapshot newest-first
 * and navigates to the selected revision via `?revision=N`. Rebuilt against
 * the M9 design tokens — uses the shadcn DropdownMenu primitive instead of a
 * flat row of pills so the chrome scales when a snapshot accumulates dozens
 * of revisions.
 */
export function VersionSwitcher({ workbenchSlug, snapshotSlug, versions, className, onSelectHistorical }: Props) {
    if (versions.length <= 1) {
        return null;
    }

    const current = versions.find((v) => v.is_current) ?? versions[0];

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    data-testid="nexus-version-switcher"
                    className={cn('inline-flex items-center gap-2', className)}
                    aria-label="Snapshot Revisions"
                >
                    <History className="size-4" aria-hidden />
                    <span>Revision r{current.revision}</span>
                    <span className="text-xs text-muted-foreground">
                        of {versions.length}
                    </span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-[14rem]">
                <DropdownMenuLabel className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                    Revisions
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                {versions.map((version) => (
                    <DropdownMenuItem
                        key={version.id}
                        asChild
                        aria-current={version.is_current ? 'true' : undefined}
                    >
                        <Link
                            href={buildHref(workbenchSlug, snapshotSlug, version.revision)}
                            preserveScroll
                            onClick={() => {
                                if (!version.is_current) {
                                    onSelectHistorical?.();
                                }
                            }}
                            className="flex w-full items-center justify-between gap-3"
                        >
                            <span className="flex items-center gap-2">
                                <span className="font-mono text-xs text-muted-foreground">
                                    r{version.revision}
                                </span>
                                <span className="text-sm capitalize">
                                    {version.view_type.replace('_', ' ')}
                                </span>
                            </span>
                            {version.is_current ? (
                                <Check className="size-4 text-foreground" aria-hidden />
                            ) : null}
                        </Link>
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function buildHref(workbenchSlug: string, snapshotSlug: string, revision: number): string {
    const url = new URL(`/workbenches/${workbenchSlug}/snapshots/${snapshotSlug}`, window.location.origin);
    url.searchParams.set('revision', String(revision));

    return `${url.pathname}${url.search}`;
}
