import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import ReactMarkdown from 'react-markdown';
import type { Components } from 'react-markdown';
import remarkGfm from 'remark-gfm';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
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
    /** 'presentation' takes over the viewport (cream full-bleed). 'embedded' stays inline. */
    mode?: 'presentation' | 'embedded';
};

/**
 * REQ-M3-002: Slide Deck view supports keyboard navigation.
 *  - ArrowLeft: previous slide
 *  - ArrowRight: next slide
 *  - Space: next slide (preventDefault to avoid page scroll)
 *
 * REQ-M9-011: visual chrome rebuilt against the shadcn token scale. The
 * embedded mode now wraps each slide in a `<Card>`, the prev/next buttons
 * use shadcn `<Button>` primitives, and font sizes consume the M9 type
 * tokens. Presentation mode (full-bleed parchment) keeps its bespoke
 * typography because it is intentionally outside the chrome system.
 */
export function SlideDeckView({ payload, className, mode = 'presentation' }: Props) {
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

    const goPrev = useCallback(() => setIndex((c) => Math.max(0, c - 1)), []);
    const goNext = useCallback(
        () => setIndex((c) => Math.min(slides.length - 1, c + 1)),
        [slides.length],
    );

    if (slides.length === 0) {
        return (
            <div
                data-testid="nexus-slide-deck-view"
                className={cn(
                    'rounded-lg border border-dashed border-border p-8 text-sm text-muted-foreground',
                    className,
                )}
            >
                No slides to display.
            </div>
        );
    }

    const current = slides[Math.min(index, slides.length - 1)];
    const isPresentation = mode === 'presentation';

    return (
        <div
            data-testid="nexus-slide-deck-view"
            data-slide-index={index}
            data-slide-count={slides.length}
            tabIndex={0}
            className={cn(
                // Full-bleed parchment in presentation mode; inline card otherwise.
                isPresentation
                    ? 'fixed inset-0 z-50 flex flex-col bg-gradient-to-br from-stone-50 via-stone-100 to-emerald-50/40 font-serif text-neutral-900 outline-none dark:from-neutral-950 dark:via-neutral-900 dark:to-neutral-950 dark:text-neutral-100'
                    : 'flex w-full flex-col gap-4',
                className,
            )}
        >
            {isPresentation ? (
                <section className="flex flex-1 items-center justify-center px-8 pt-16 pb-8">
                    <article className="mx-auto w-full max-w-3xl">
                        <h2 className="mb-8 font-serif text-4xl font-medium tracking-tight md:text-5xl">
                            {current.title}
                        </h2>
                        <SlideMarkdown body={current.body_md} presentation={isPresentation} />
                    </article>
                </section>
            ) : (
                <Card className="gap-0 rounded-lg px-8 py-8 shadow-sm">
                    <article className="w-full">
                        <h2 className="mb-4 text-xl font-semibold tracking-tight">{current.title}</h2>
                        <SlideMarkdown body={current.body_md} presentation={isPresentation} />
                    </article>
                </Card>
            )}

            {isPresentation ? (
                <div className="flex items-center justify-between px-8 pb-8">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        onClick={goPrev}
                        disabled={index === 0}
                        aria-label="Previous slide"
                        className="rounded-full text-neutral-600 hover:bg-neutral-900/5 dark:text-neutral-300 dark:hover:bg-neutral-100/5"
                    >
                        <ChevronLeft className="size-5" strokeWidth={1.5} />
                    </Button>
                    <div className="flex items-center gap-4">
                        <SlideDots count={slides.length} active={index} onSelect={setIndex} />
                        <span className="text-xs uppercase tracking-widest tabular-nums text-neutral-500 dark:text-neutral-400">
                            {index + 1} / {slides.length}
                        </span>
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        onClick={goNext}
                        disabled={index === slides.length - 1}
                        aria-label="Next slide"
                        className="rounded-full text-neutral-600 hover:bg-neutral-900/5 dark:text-neutral-300 dark:hover:bg-neutral-100/5"
                    >
                        <ChevronRight className="size-5" strokeWidth={1.5} />
                    </Button>
                </div>
            ) : (
                <div className="flex items-center justify-between text-xs text-muted-foreground">
                    <span>
                        Slide {index + 1} of {slides.length}
                    </span>
                    <div className="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={goPrev}
                            disabled={index === 0}
                        >
                            Previous
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={goNext}
                            disabled={index === slides.length - 1}
                        >
                            Next
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}

function SlideDots({
    count,
    active,
    onSelect,
}: {
    count: number;
    active: number;
    onSelect: (i: number) => void;
}) {
    return (
        <div className="flex items-center gap-2">
            {Array.from({ length: count }).map((_, i) => (
                <button
                    key={i}
                    type="button"
                    aria-label={`Go to slide ${i + 1}`}
                    onClick={() => onSelect(i)}
                    className={cn(
                        'h-1.5 rounded-full transition-all',
                        i === active
                            ? 'w-6 bg-neutral-900 dark:bg-neutral-100'
                            : 'w-1.5 bg-neutral-400/60 hover:bg-neutral-500 dark:bg-neutral-600 dark:hover:bg-neutral-400',
                    )}
                />
            ))}
        </div>
    );
}

/**
 * Presentation-mode markdown: generous prose sizing, real tables, real code blocks.
 * Uses react-markdown + remark-gfm so pipe-tables render as <table>.
 */
function SlideMarkdown({ body, presentation }: { body: string; presentation: boolean }) {
    const components = useMemo<Components>(
        () => buildMarkdownComponents(presentation),
        [presentation],
    );

    return (
        <div
            className={cn(
                'slide-prose',
                presentation ? 'text-lg leading-relaxed md:text-xl' : 'text-sm',
            )}
        >
            <ReactMarkdown remarkPlugins={[remarkGfm]} components={components}>
                {body}
            </ReactMarkdown>
        </div>
    );
}

type MdProps = {
    children?: React.ReactNode;
    className?: string;
    href?: string;
};

function buildMarkdownComponents(presentation: boolean): Components {
    const h = presentation ? 'font-medium tracking-tight' : 'font-semibold';

    return {
        h1: ({ children }: MdProps) => <h3 className={cn('mb-4 text-2xl', h)}>{children}</h3>,
        h2: ({ children }: MdProps) => <h4 className={cn('mb-4 text-xl', h)}>{children}</h4>,
        h3: ({ children }: MdProps) => <h5 className={cn('mb-4 text-lg', h)}>{children}</h5>,
        p: ({ children }: MdProps) => <p className="mb-4 last:mb-0">{children}</p>,
        ul: ({ children }: MdProps) => (
            <ul className="mb-4 list-disc space-y-1 pl-8 marker:text-neutral-400 last:mb-0">
                {children}
            </ul>
        ),
        ol: ({ children }: MdProps) => (
            <ol className="mb-4 list-decimal space-y-1 pl-8 marker:text-neutral-400 last:mb-0">
                {children}
            </ol>
        ),
        li: ({ children }: MdProps) => <li className="pl-1">{children}</li>,
        strong: ({ children }: MdProps) => (
            <strong className="font-semibold text-neutral-900 dark:text-neutral-50">{children}</strong>
        ),
        em: ({ children }: MdProps) => <em className="italic">{children}</em>,
        a: ({ children, href }: MdProps) => (
            <a
                href={href}
                target="_blank"
                rel="noreferrer noopener"
                className="underline decoration-neutral-400 underline-offset-4 hover:decoration-current"
            >
                {children}
            </a>
        ),
        blockquote: ({ children }: MdProps) => (
            <blockquote className="my-4 border-l-2 border-neutral-400 pl-4 italic text-neutral-700 dark:text-neutral-300">
                {children}
            </blockquote>
        ),
        hr: () => <hr className="my-8 border-neutral-300 dark:border-neutral-700" />,
        code: ({ className, children, ...rest }: MdProps) => {
            const isBlock = /language-/.test(className ?? '');

            if (isBlock) {
                return (
                    <code
                        className={cn(
                            'block whitespace-pre font-mono text-sm leading-relaxed',
                            className,
                        )}
                        {...rest}
                    >
                        {children}
                    </code>
                );
            }

            return (
                <code
                    className="rounded-sm bg-neutral-900/5 px-1.5 py-0.5 font-mono text-sm text-neutral-900 dark:bg-neutral-100/10 dark:text-neutral-100"
                    {...rest}
                >
                    {children}
                </code>
            );
        },
        pre: ({ children }: MdProps) => (
            <pre className="mb-4 overflow-x-auto rounded-md border border-neutral-200 bg-neutral-50 p-4 text-sm last:mb-0 dark:border-neutral-800 dark:bg-neutral-900">
                {children}
            </pre>
        ),
        table: ({ children }: MdProps) => (
            <div className="mb-4 overflow-x-auto rounded-md border border-neutral-200 bg-white/60 shadow-xs last:mb-0 dark:border-neutral-800 dark:bg-neutral-900/40">
                <table className="w-full border-collapse text-left text-base">{children}</table>
            </div>
        ),
        thead: ({ children }: MdProps) => (
            <thead className="border-b border-neutral-200 bg-neutral-50/80 dark:border-neutral-800 dark:bg-neutral-900/60">
                {children}
            </thead>
        ),
        tbody: ({ children }: MdProps) => <tbody>{children}</tbody>,
        tr: ({ children }: MdProps) => (
            <tr className="border-b border-neutral-100 last:border-0 dark:border-neutral-800/60">
                {children}
            </tr>
        ),
        th: ({ children }: MdProps) => (
            <th className="px-4 py-2 text-sm font-semibold text-neutral-700 dark:text-neutral-300">
                {children}
            </th>
        ),
        td: ({ children }: MdProps) => (
            <td className="px-4 py-2 text-base text-neutral-800 dark:text-neutral-200">
                {children}
            </td>
        ),
    };
}
