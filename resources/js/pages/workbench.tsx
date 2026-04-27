import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    Bot,
    Clock3,
    MessageSquareText,
    Share2,
} from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { agentActivity } from '@/routes/workbench';
import { show as showSnapshot } from '@/routes/workbench/snapshot';

type Workbench = {
    slug: string;
    name: string;
};

type ViewEntry = {
    slug: string;
    title: string | null;
    current_revision: number | null;
    view_type: string | null;
    last_activity_at: string | null;
    last_activity_human: string | null;
    visibility: 'private' | 'link' | 'shared' | string | null;
    active_share_count: number;
    has_link_share: boolean;
    open_comment_count: number;
};

type Props = {
    workbench: Workbench;
    views: ViewEntry[];
};

const viewLabel = (viewType: string | null): string => {
    if (!viewType) {
        return 'View';
    }

    return viewType.replaceAll('_', ' ');
};

function ViewBadges({ view }: { view: ViewEntry }) {
    const hasShares =
        view.has_link_share ||
        view.active_share_count > 0 ||
        view.visibility === 'shared';

    return (
        <div className="flex flex-wrap items-center gap-2">
            <Badge variant="outline" className="capitalize">
                {viewLabel(view.view_type)}
            </Badge>
            {view.current_revision ? (
                <Badge variant="secondary">v{view.current_revision}</Badge>
            ) : null}
            {hasShares ? (
                <Badge variant="outline" className="gap-1.5">
                    <Share2 className="size-3" />
                    Shared
                </Badge>
            ) : null}
            {view.open_comment_count > 0 ? (
                <Badge variant="outline" className="gap-1.5">
                    <MessageSquareText className="size-3" />
                    {view.open_comment_count}
                </Badge>
            ) : null}
        </div>
    );
}

function EmptyProject({ workbench }: { workbench: Workbench }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>No Views Yet</CardTitle>
                <CardDescription>
                    Views appear here after an agent sends structured data to
                    this project. Agent Activity is available as the project
                    placeholder while the first view is created.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Button asChild>
                    <Link
                        href={agentActivity({ workbench: workbench.slug }).url}
                    >
                        <Bot className="size-4" />
                        Open Agent Activity
                    </Link>
                </Button>
            </CardContent>
        </Card>
    );
}

function ViewRow({
    workbench,
    view,
}: {
    workbench: Workbench;
    view: ViewEntry;
}) {
    const href = showSnapshot({
        workbench: workbench.slug,
        snapshot: view.slug,
    }).url;

    return (
        <li>
            <Link
                href={href}
                prefetch
                className="flex items-center gap-4 px-4 py-4 transition-colors hover:bg-muted focus-visible:bg-muted focus-visible:outline-none"
            >
                <div className="min-w-0 flex-1">
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div className="min-w-0">
                            <div className="truncate text-sm font-medium text-foreground">
                                {view.title ?? view.slug}
                            </div>
                            <div className="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                                <Clock3 className="size-3" />
                                {view.last_activity_human
                                    ? `${view.last_activity_human} ago`
                                    : 'No revisions yet'}
                            </div>
                        </div>
                        <ViewBadges view={view} />
                    </div>
                </div>
                <ArrowRight className="size-4 shrink-0 text-muted-foreground" />
            </Link>
        </li>
    );
}

export default function WorkbenchPage({ workbench, views }: Props) {
    return (
        <>
            <Head title={workbench.name} />

            <div className="border-b bg-background">
                <div className="mx-auto flex w-full max-w-6xl flex-col gap-3 px-6 py-6">
                    <div className="flex flex-wrap items-center gap-3">
                        <h1 className="text-3xl font-semibold tracking-tight">
                            {workbench.name}
                        </h1>
                        <Badge variant="secondary">Project</Badge>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {views.length === 1
                            ? '1 view in this project'
                            : `${views.length} views in this project`}
                    </p>
                </div>
            </div>

            <main className="mx-auto flex w-full max-w-6xl flex-col gap-6 px-6 py-8">
                <section className="flex flex-col gap-4">
                    <div className="flex items-baseline justify-between gap-3">
                        <h2 className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            Views
                        </h2>
                        <span className="text-xs text-muted-foreground">
                            sorted by last activity
                        </span>
                    </div>

                    {views.length === 0 ? (
                        <EmptyProject workbench={workbench} />
                    ) : (
                        <Card className="gap-0 overflow-hidden p-0">
                            <ul className="divide-y">
                                {views.map((view) => (
                                    <ViewRow
                                        key={view.slug}
                                        workbench={workbench}
                                        view={view}
                                    />
                                ))}
                            </ul>
                        </Card>
                    )}
                </section>
            </main>
        </>
    );
}

WorkbenchPage.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: '/dashboard',
        },
        {
            title: 'Project',
            href: '#',
        },
    ],
};
