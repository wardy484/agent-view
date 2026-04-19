import { Head, Link } from '@inertiajs/react';
import { ArrowRight, KeyRound } from 'lucide-react';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import { dashboard } from '@/routes';
import { edit as editTokens } from '@/routes/tokens';

export default function Dashboard() {
    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <Link
                        href={editTokens()}
                        prefetch
                        className="group relative flex aspect-video flex-col justify-between overflow-hidden rounded-xl border border-sidebar-border/70 bg-card p-5 transition-colors hover:border-primary/60 hover:bg-muted/40 dark:border-sidebar-border"
                    >
                        <div className="flex items-center gap-3">
                            <span className="flex size-10 items-center justify-center rounded-md bg-primary/10 text-primary">
                                <KeyRound className="size-5" />
                            </span>
                            <h2 className="text-base font-semibold">
                                API tokens
                            </h2>
                        </div>
                        <div className="flex items-end justify-between gap-2">
                            <p className="text-sm text-muted-foreground">
                                Mint and manage Sanctum tokens for the MCP
                                endpoint.
                            </p>
                            <ArrowRight className="size-4 text-muted-foreground transition-transform group-hover:translate-x-1 group-hover:text-foreground" />
                        </div>
                    </Link>
                    <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                    <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                </div>
                <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
                    <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
