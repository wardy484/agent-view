import { useEffect, useState } from 'react';

import { cn } from '@/lib/utils';

export type Slide = {
    title: string;
    body_md: string;
};

export type SlideDeckViewPayload = {
    slides: Slide[];
};

type Props = {
    payload: SlideDeckViewPayload;
    className?: string;
    /** When true, the deck fills the available viewport (preview / fullscreen mode). */
    fullBleed?: boolean;
};

/**
 * REQ-M3-002: Slide Deck view supports keyboard navigation.
 *  - ArrowLeft: previous slide
 *  - ArrowRight: next slide
 *  - Space: next slide (preventDefault to avoid page scroll)
 */
export function SlideDeckView({ payload, className, fullBleed = false }: Props) {
    const slides = payload?.slides ?? [];
    const [index, setIndex] = useState(0);

    useEffect(() => {
        function handleKeyDown(event: KeyboardEvent) {
            if (event.key === 'ArrowLeft') {
                setIndex((current) => Math.max(0, current - 1));
            } else if (event.key === 'ArrowRight') {
                setIndex((current) => Math.min(slides.length - 1, current + 1));
            } else if (event.key === ' ' || event.code === 'Space') {
                event.preventDefault();
                setIndex((current) => Math.min(slides.length - 1, current + 1));
            }
        }

        window.addEventListener('keydown', handleKeyDown);

        return () => {
            window.removeEventListener('keydown', handleKeyDown);
        };
    }, [slides.length]);

    if (slides.length === 0) {
        return (
            <div
                data-testid="nexus-slide-deck-view"
                className={cn('rounded-lg border border-dashed border-border p-6 text-sm text-muted-foreground', className)}
            >
                No slides to display.
            </div>
        );
    }

    const current = slides[Math.min(index, slides.length - 1)];

    return (
        <div
            data-testid="nexus-slide-deck-view"
            data-slide-index={index}
            data-slide-count={slides.length}
            data-full-bleed={fullBleed}
            tabIndex={0}
            className={cn(
                'flex w-full flex-col gap-3',
                fullBleed && 'h-screen min-h-screen gap-0',
                className,
            )}
        >
            <section
                className={cn(
                    'rounded-lg border border-border bg-background p-6',
                    fullBleed && 'flex flex-1 flex-col justify-center rounded-none border-0 px-12 py-16',
                )}
            >
                <h2 className={cn('mb-3 text-xl font-semibold', fullBleed && 'text-4xl')}>{current.title}</h2>
                <pre
                    className={cn(
                        'whitespace-pre-wrap font-sans text-sm text-foreground',
                        fullBleed && 'text-xl leading-relaxed',
                    )}
                >
                    {current.body_md}
                </pre>
            </section>

            <div
                className={cn(
                    'flex items-center justify-between text-xs text-muted-foreground',
                    fullBleed && 'border-t border-border bg-background/80 px-6 py-3 backdrop-blur',
                )}
            >
                <span>
                    Slide {index + 1} of {slides.length}
                </span>
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={() => setIndex((current) => Math.max(0, current - 1))}
                        disabled={index === 0}
                        className="rounded-md border border-border px-2 py-1 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Previous
                    </button>
                    <button
                        type="button"
                        onClick={() => setIndex((current) => Math.min(slides.length - 1, current + 1))}
                        disabled={index === slides.length - 1}
                        className="rounded-md border border-border px-2 py-1 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    );
}
