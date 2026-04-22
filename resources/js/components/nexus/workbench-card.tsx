import { Link, router } from '@inertiajs/react';
import {
    Archive,
    ArchiveRestore,
    Check,
    MoreHorizontal,
    Pencil,
    Pin,
    PinOff,
    RotateCcw,
    Trash2,
    X,
} from 'lucide-react';
import type {
    KeyboardEvent,
    MouseEvent} from 'react';
import {
    useCallback,
    useEffect,
    useRef,
    useState,
} from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import {
    archive as archiveRoute,
    deleteMethod as deleteRoute,
    pin as pinRoute,
    rename as renameRoute,
    restore as restoreRoute,
    unarchive as unarchiveRoute,
    unpin as unpinRoute,
} from '@/routes/workbench';
import { show as showSnapshot } from '@/routes/workbench/snapshot';

export type WorkbenchItem = {
    slug: string;
    name: string;
    snapshot_count: number;
    pinned_at: string | null;
    archived_at: string | null;
    deleted_at: string | null;
    last_activity_at: string | null;
    last_activity_human: string | null;
    latest_snapshot_slug: string | null;
};

type Tab = 'active' | 'archived' | 'trash';

type Props = {
    workbench: WorkbenchItem;
    tab: Tab;
};

const xsrfToken = (): string => {
    if (typeof document === 'undefined') {
        return '';
    }

    const match = document.cookie
        .split('; ')
        .find((c) => c.startsWith('XSRF-TOKEN='));

    return match ? decodeURIComponent(match.split('=')[1]) : '';
};

async function mutate(
    url: string,
    method: 'POST' | 'PATCH' | 'DELETE',
    body?: Record<string, unknown>,
): Promise<Response> {
    const res = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: body ? JSON.stringify(body) : undefined,
    });

    if (!res.ok) {
        throw new Error(`Request failed (${res.status})`);
    }

    return res;
}

const refreshWorkbenches = () =>
    router.reload({ only: ['ownedWorkbenches', 'workbenches'] });

export function WorkbenchCard({ workbench, tab }: Props) {
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [renaming, setRenaming] = useState(false);
    const [draftName, setDraftName] = useState(workbench.name);
    const inputRef = useRef<HTMLInputElement | null>(null);

    useEffect(() => {
        if (renaming) {
            inputRef.current?.focus();
            inputRef.current?.select();
        }
    }, [renaming]);

    const isPinned = workbench.pinned_at !== null;

    const href = workbench.latest_snapshot_slug
        ? showSnapshot({
              workbench: workbench.slug,
              snapshot: workbench.latest_snapshot_slug,
          }).url
        : null;

    const run = useCallback(
        async (action: () => Promise<unknown>) => {
            if (busy) {
return;
}

            setBusy(true);
            setError(null);

            try {
                await action();
                await refreshWorkbenches();
            } catch (e) {
                const message = e instanceof Error ? e.message : 'Request failed';
                setError(message);
            } finally {
                setBusy(false);
            }
        },
        [busy],
    );

    const onRenameSubmit = async () => {
        const name = draftName.trim();

        if (name.length === 0 || name === workbench.name) {
            setRenaming(false);
            setDraftName(workbench.name);

            return;
        }

        const route = renameRoute(workbench.slug);
        await run(() => mutate(route.url, 'PATCH', { name }));
        setRenaming(false);
    };

    const onRenameKey = (e: KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            void onRenameSubmit();
        } else if (e.key === 'Escape') {
            e.preventDefault();
            setRenaming(false);
            setDraftName(workbench.name);
        }
    };

    const stopAnchor = (e: MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
    };

    const onPin = () =>
        run(() => mutate(pinRoute(workbench.slug).url, 'POST'));
    const onUnpin = () =>
        run(() => mutate(unpinRoute(workbench.slug).url, 'DELETE'));
    const onArchive = () =>
        run(() => mutate(archiveRoute(workbench.slug).url, 'POST'));
    const onUnarchive = () =>
        run(() => mutate(unarchiveRoute(workbench.slug).url, 'DELETE'));
    const onDelete = () => {
        if (
            !window.confirm(
                `Move "${workbench.name}" to Trash? You can restore it within 30 days.`,
            )
        ) {
            return;
        }

        return run(() => mutate(deleteRoute(workbench.slug).url, 'DELETE'));
    };
    const onRestore = () =>
        run(() => mutate(restoreRoute(workbench.slug).url, 'POST'));

    const isLinked = tab === 'active' && href !== null;
    const wrapperClass =
        'nx-card group relative flex min-h-[136px] flex-col gap-3 p-4' +
        (isLinked ? ' hover:border-[color:var(--fg-3)]' : '');

    const inner = (
        <>
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0 flex-1">
                    {renaming ? (
                        <div
                            className="flex items-center gap-1"
                            onClick={stopAnchor}
                        >
                            <input
                                ref={inputRef}
                                type="text"
                                value={draftName}
                                onChange={(e) => setDraftName(e.target.value)}
                                onKeyDown={onRenameKey}
                                maxLength={120}
                                className="w-full rounded-sm border border-[color:var(--line)] bg-[color:var(--bg-2)] px-2 py-1 font-serif text-[15px] text-[color:var(--fg-0)] outline-none focus:border-[color:var(--fg-2)]"
                                data-test="workbench-rename-input"
                            />
                            <button
                                type="button"
                                onClick={onRenameSubmit}
                                disabled={busy}
                                className="rounded-sm p-1 text-[color:var(--fg-2)] hover:bg-[color:var(--bg-2)] hover:text-[color:var(--fg-0)]"
                                aria-label="Save rename"
                            >
                                <Check size={14} />
                            </button>
                            <button
                                type="button"
                                onClick={() => {
                                    setRenaming(false);
                                    setDraftName(workbench.name);
                                }}
                                className="rounded-sm p-1 text-[color:var(--fg-2)] hover:bg-[color:var(--bg-2)] hover:text-[color:var(--fg-0)]"
                                aria-label="Cancel rename"
                            >
                                <X size={14} />
                            </button>
                        </div>
                    ) : (
                        <>
                            <div className="flex items-center gap-1.5">
                                {isPinned && (
                                    <Pin
                                        size={12}
                                        className="text-[color:var(--fg-2)]"
                                        aria-label="Pinned"
                                    />
                                )}
                                <h3 className="truncate font-serif text-[15px] leading-tight text-[color:var(--fg-0)]">
                                    {workbench.name}
                                </h3>
                            </div>
                            <div className="mt-1 truncate font-mono text-[11px] text-[color:var(--fg-3)]">
                                /{workbench.slug}
                            </div>
                        </>
                    )}
                </div>

                {tab !== 'trash' || workbench.deleted_at !== null ? (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild onClick={stopAnchor}>
                            <button
                                type="button"
                                className="rounded-sm p-1 text-[color:var(--fg-3)] hover:bg-[color:var(--bg-2)] hover:text-[color:var(--fg-0)]"
                                aria-label="Open menu"
                                data-test="workbench-kebab"
                                disabled={busy}
                            >
                                <MoreHorizontal size={16} />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            align="end"
                            onClick={stopAnchor}
                            className="min-w-[160px]"
                        >
                            {tab === 'trash' ? (
                                <DropdownMenuItem
                                    onSelect={() => void onRestore()}
                                    data-test="workbench-restore"
                                >
                                    <RotateCcw />
                                    Restore
                                </DropdownMenuItem>
                            ) : (
                                <>
                                    <DropdownMenuItem
                                        onSelect={() => setRenaming(true)}
                                        data-test="workbench-rename"
                                    >
                                        <Pencil />
                                        Rename
                                    </DropdownMenuItem>
                                    {tab === 'active' &&
                                        (isPinned ? (
                                            <DropdownMenuItem
                                                onSelect={() => void onUnpin()}
                                                data-test="workbench-unpin"
                                            >
                                                <PinOff />
                                                Unpin
                                            </DropdownMenuItem>
                                        ) : (
                                            <DropdownMenuItem
                                                onSelect={() => void onPin()}
                                                data-test="workbench-pin"
                                            >
                                                <Pin />
                                                Pin
                                            </DropdownMenuItem>
                                        ))}
                                    {tab === 'active' ? (
                                        <DropdownMenuItem
                                            onSelect={() => void onArchive()}
                                            data-test="workbench-archive"
                                        >
                                            <Archive />
                                            Archive
                                        </DropdownMenuItem>
                                    ) : (
                                        <DropdownMenuItem
                                            onSelect={() => void onUnarchive()}
                                            data-test="workbench-unarchive"
                                        >
                                            <ArchiveRestore />
                                            Unarchive
                                        </DropdownMenuItem>
                                    )}
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        variant="destructive"
                                        onSelect={() => void onDelete()}
                                        data-test="workbench-delete"
                                    >
                                        <Trash2 />
                                        Delete
                                    </DropdownMenuItem>
                                </>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                ) : null}
            </div>

            <div className="mt-auto flex items-center justify-between font-mono text-[11px] text-[color:var(--fg-3)]">
                <span>
                    {workbench.snapshot_count === 1
                        ? '1 snapshot'
                        : `${workbench.snapshot_count} snapshots`}
                </span>
                <span
                    className={cn(
                        'tabular-nums',
                        busy && 'opacity-60',
                    )}
                >
                    {workbench.last_activity_human
                        ? `${workbench.last_activity_human} ago`
                        : 'no activity yet'}
                </span>
            </div>

            {error && (
                <div className="rounded-sm border border-red-200 bg-red-50 px-2 py-1 font-mono text-[11px] text-red-700 dark:border-red-400/20 dark:bg-red-500/10 dark:text-red-300">
                    {error}
                </div>
            )}
        </>
    );

    if (isLinked && href) {
        return (
            <Link href={href} prefetch className={wrapperClass}>
                {inner}
            </Link>
        );
    }

    return <div className={wrapperClass}>{inner}</div>;
}
