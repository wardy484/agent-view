import type { Auth } from '@/types/auth';

export type SharedWorkbench = {
    slug: string;
    name: string;
    snapshot_count: number;
    latest_snapshot_slug: string | null;
};

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            workbenches: SharedWorkbench[];
            [key: string]: unknown;
        };
    }
}
