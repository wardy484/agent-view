import type { PropsWithChildren } from 'react';

type LegalLayoutProps = PropsWithChildren<{
    title: string;
    lastUpdated: string;
    intro?: string;
}>;

export default function LegalLayout({
    title,
    lastUpdated,
    intro,
    children,
}: LegalLayoutProps) {
    return (
        <div className="mx-auto w-full max-w-3xl px-6 py-16">
            <header className="mb-10 border-b border-border/60 pb-6">
                <p className="text-xs font-medium tracking-widest text-muted-foreground uppercase">
                    Legal
                </p>
                <h1 className="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">
                    {title}
                </h1>
                <p className="mt-3 text-sm text-muted-foreground">
                    Last updated: {lastUpdated}
                </p>
                {intro && (
                    <p className="mt-4 text-base text-muted-foreground">
                        {intro}
                    </p>
                )}
            </header>
            <article className="prose prose-neutral dark:prose-invert max-w-none space-y-6 text-sm leading-6 text-foreground [&_h2]:mt-10 [&_h2]:mb-3 [&_h2]:text-xl [&_h2]:font-semibold [&_h3]:mt-6 [&_h3]:mb-2 [&_h3]:text-base [&_h3]:font-semibold [&_p]:text-muted-foreground [&_ul]:list-disc [&_ul]:pl-6 [&_ul]:text-muted-foreground [&_li]:mb-1 [&_a]:text-foreground [&_a]:underline">
                {children}
            </article>
        </div>
    );
}
