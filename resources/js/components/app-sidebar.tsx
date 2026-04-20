import { Link, usePage } from '@inertiajs/react';
import { KeyRound, LayoutGrid, Users2 } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { edit as editTokens } from '@/routes/tokens';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'API Tokens',
        href: editTokens(),
        icon: KeyRound,
    },
];

// REQ-M4-007: shape of the `sharing` prop injected by HandleInertiaRequests.
type SharedSnapshot = {
    snapshot_id: number;
    snapshot_slug: string;
    snapshot_title: string | null;
    workbench_slug: string;
    workbench_name: string;
    url: string;
};

type SharingPayload = {
    shared_with_me: SharedSnapshot[];
    owned_badges: Array<{
        snapshot_id: number;
        snapshot_slug: string;
        workbench_slug: string;
        share_count: number;
        has_link: boolean;
    }>;
};

export function AppSidebar() {
    const page = usePage<{ sharing?: SharingPayload }>();
    const sharedWithMe = page.props.sharing?.shared_with_me ?? [];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />

                {sharedWithMe.length > 0 ? (
                    <SidebarGroup data-testid="sidebar-shared-with-me">
                        <SidebarGroupLabel>
                            <Users2 className="size-4" />
                            Shared with me
                        </SidebarGroupLabel>
                        <SidebarGroupContent>
                            <SidebarMenu>
                                {sharedWithMe.map((entry) => (
                                    <SidebarMenuItem key={entry.snapshot_id}>
                                        <SidebarMenuButton asChild>
                                            <Link href={entry.url} prefetch>
                                                <span className="truncate">
                                                    {entry.snapshot_title ?? entry.snapshot_slug}
                                                </span>
                                                <span className="ml-auto text-xs text-muted-foreground">
                                                    {entry.workbench_name}
                                                </span>
                                            </Link>
                                        </SidebarMenuButton>
                                    </SidebarMenuItem>
                                ))}
                            </SidebarMenu>
                        </SidebarGroupContent>
                    </SidebarGroup>
                ) : null}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
