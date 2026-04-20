import type { ComponentType } from 'react';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import MarketingLayout from '@/layouts/marketing-layout';
import SettingsLayout from '@/layouts/settings/layout';

const marketingPages = new Set(['contact']);

// Pages that render their own chrome and must bypass every global layout.
// `snapshot` switches between app / preview / fullscreen chrome itself and
// wraps its own body in AppLayout in app mode — letting the resolver pick
// AppLayout here would double-wrap the sidebar.
const standalonePages = new Set(['welcome', 'snapshot']);

type Layout = ComponentType<{ children: React.ReactNode }>;

/**
 * Picks the default layout for a given Inertia page name.
 *
 * Returns `null` for standalone pages that render their own chrome.
 *
 * Keep this as the single source of truth — both the client entry
 * (`app.tsx`) and the SSR entry (`ssr.tsx`) must resolve layouts the
 * same way, otherwise SSR HTML and client hydration HTML diverge and
 * the layout flashes in and then vanishes on the client.
 *
 * This function is passed to createInertiaApp({ layout }), which maps
 * to the App component's `defaultLayout` prop. Pages that set their
 * own `.layout` on the component override this; pages that set
 * `.layout = { ...props }` (v3 layout-props pattern) still get the
 * layout component from here.
 */
export function pickLayout(name: string): Layout | Layout[] | null {
    switch (true) {
        case standalonePages.has(name):
            return null;
        case marketingPages.has(name):
        case name.startsWith('legal/'):
            return MarketingLayout;
        case name.startsWith('auth/'):
            return AuthLayout;
        case name.startsWith('settings/'):
            return [AppLayout, SettingsLayout];
        default:
            return AppLayout;
    }
}
