import { Link } from '@inertiajs/react';
import { Home } from 'lucide-react';

import { cn } from '@/lib/utils';

type Props = {
    isAuthenticated: boolean;
    className?: string;
};

/**
 * REQ-M3-012 / REQ-M3-013: subtle floating "home" link surfaced when a
 * snapshot renders bare (preview mode) or when the in-app fullscreen toggle
 * is active. Auth-aware: logged-in users land on the workbench index;
 * guests land on the marketing home.
 */
export function PreviewHomeButton({ isAuthenticated, className }: Props) {
    const href = isAuthenticated ? '/dashboard' : '/';
    const label = isAuthenticated ? 'Back to your dashboard' : 'Nexus-UI home';

    return (
        <Link
            href={href}
            data-testid="nexus-preview-home-button"
            aria-label={label}
            title={label}
            className={cn(
                'fixed top-3 right-3 z-[60] flex h-9 w-9 items-center justify-center rounded-full border border-border bg-background/70 text-muted-foreground opacity-50 shadow-sm backdrop-blur transition hover:text-foreground hover:opacity-100',
                className,
            )}
        >
            <Home className="size-4" aria-hidden />
        </Link>
    );
}
