import { cn } from '@/lib/utils';

type Props = {
    pinned: boolean;
    onToggle: () => void;
    className?: string;
};

export function PinToLatestToggle({ pinned, onToggle, className }: Props) {
    return (
        <label
            className={cn(
                'inline-flex h-9 cursor-pointer items-center gap-2 rounded-md border border-border bg-background px-3 text-xs font-medium text-muted-foreground shadow-sm select-none hover:text-foreground',
                className,
            )}
            title={
                pinned
                    ? 'Auto-jumping to the latest revision when one arrives'
                    : 'Stay on the current revision when new ones arrive'
            }
        >
            <span>Pin to latest</span>
            <button
                type="button"
                role="switch"
                aria-checked={pinned}
                onClick={onToggle}
                data-testid="nexus-pin-to-latest-toggle"
                className={cn(
                    'relative inline-flex h-4 w-7 shrink-0 items-center rounded-full transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring',
                    pinned ? 'bg-primary' : 'bg-input',
                )}
            >
                <span
                    aria-hidden
                    className={cn(
                        'inline-block size-3 rounded-full bg-background shadow transition-transform',
                        pinned ? 'translate-x-3.5' : 'translate-x-0.5',
                    )}
                />
            </button>
        </label>
    );
}
