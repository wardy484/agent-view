import { useEffect, useRef, useState } from 'react';

import { cn } from '@/lib/utils';

export type FlowchartViewPayload = {
    mermaid_source: string;
};

type Props = {
    payload: FlowchartViewPayload;
    className?: string;
};

/**
 * REQ-M2-005: Flowchart view renders Mermaid source via Mermaid.js in the
 * browser.
 *
 * Mermaid is imported lazily so the main bundle isn't bloated when the user
 * never opens a flowchart snapshot. On SSR/pre-hydration the component
 * falls back to a monospaced preview of the source.
 */
export function FlowchartView({ payload, className }: Props) {
    const source = payload?.mermaid_source ?? '';
    const containerRef = useRef<HTMLDivElement | null>(null);
    const [svg, setSvg] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;

        async function renderMermaid() {
            if (source.trim() === '') {
                setSvg(null);
                setError(null);

                return;
            }

            try {
                const mermaidModule = await import('mermaid');
                const mermaid = mermaidModule.default;
                mermaid.initialize({ startOnLoad: false, securityLevel: 'strict', theme: 'default' });

                const id = `nexus-flowchart-${Math.random().toString(36).slice(2)}`;
                const { svg: renderedSvg } = await mermaid.render(id, source);

                if (!cancelled) {
                    setSvg(renderedSvg);
                    setError(null);
                }
            } catch (renderError) {
                if (!cancelled) {
                    setSvg(null);
                    setError(renderError instanceof Error ? renderError.message : String(renderError));
                }
            }
        }

        void renderMermaid();

        return () => {
            cancelled = true;
        };
    }, [source]);

    if (source.trim() === '') {
        return (
            <div
                data-testid="nexus-flowchart-view"
                className={cn('rounded-lg border border-dashed border-border p-6 text-sm text-muted-foreground', className)}
            >
                No flowchart source provided.
            </div>
        );
    }

    return (
        <div
            data-testid="nexus-flowchart-view"
            data-mermaid-rendered={svg !== null}
            className={cn('flex w-full flex-col gap-3', className)}
        >
            <div
                ref={containerRef}
                className="rounded-lg border border-border bg-background p-4"
                // Mermaid produces trusted SVG (securityLevel=strict sanitises user input).
                dangerouslySetInnerHTML={svg !== null ? { __html: svg } : undefined}
            >
                {svg === null ? (
                    <pre className="whitespace-pre-wrap font-mono text-xs text-muted-foreground">{source}</pre>
                ) : null}
            </div>

            {error !== null ? (
                <p className="text-xs text-destructive" role="alert">
                    Could not render Mermaid diagram: {error}
                </p>
            ) : null}
        </div>
    );
}
