import { createInertiaApp } from '@inertiajs/react';
import type { ResolvedComponent } from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import ReactDOMServer from 'react-dom/server';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { pickLayout } from '@/lib/resolve-layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createServer((page) =>
    createInertiaApp({
        page,
        render: ReactDOMServer.renderToString,
        title: (title) => (title ? `${title} - ${appName}` : appName),
        resolve: (name) => {
            const pages = import.meta.glob<{ default: ResolvedComponent }>(
                './pages/**/*.tsx',
            );

            return pages[`./pages/${name}.tsx`]().then((m) => m.default);
        },
        // MUST match the client `layout` in app.tsx exactly, otherwise
        // SSR HTML and client hydration HTML diverge and the layout
        // flashes in and then vanishes during hydration.
        layout: pickLayout,
        setup: ({ App, props }) => (
            <TooltipProvider delayDuration={0}>
                <App {...props} />
                <Toaster />
            </TooltipProvider>
        ),
    }),
);
