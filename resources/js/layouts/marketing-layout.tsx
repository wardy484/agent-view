import { Link, usePage } from '@inertiajs/react';
import { Menu, X } from 'lucide-react';
import { useState, type PropsWithChildren } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';

const primaryNav = [
    { label: 'Product', href: '/#product' },
    { label: 'How it works', href: '/#how-it-works' },
    { label: 'Pricing', href: '/#pricing' },
    { label: 'Docs', href: '/#docs' },
    { label: 'Contact', href: '/contact' },
];

const footerNav: Array<{
    heading: string;
    links: Array<{ label: string; href: string }>;
}> = [
    {
        heading: 'Product',
        links: [
            { label: 'Overview', href: '/#product' },
            { label: 'How it works', href: '/#how-it-works' },
            { label: 'Pricing', href: '/#pricing' },
            { label: 'Changelog', href: '/#changelog' },
        ],
    },
    {
        heading: 'Company',
        links: [
            { label: 'Contact', href: '/contact' },
            { label: 'Status', href: '/#status' },
            { label: 'Security', href: '/acceptable-use' },
        ],
    },
    {
        heading: 'Legal',
        links: [
            { label: 'Terms', href: '/terms' },
            { label: 'Privacy', href: '/privacy' },
            { label: 'Cookies', href: '/cookies' },
            { label: 'Acceptable Use', href: '/acceptable-use' },
        ],
    },
];

export default function MarketingLayout({ children }: PropsWithChildren) {
    const { auth } = usePage().props;
    const [mobileOpen, setMobileOpen] = useState(false);

    return (
        <div className="flex min-h-screen flex-col bg-background text-foreground">
            <header className="sticky top-0 z-40 border-b border-border/60 bg-background/80 backdrop-blur">
                <div className="mx-auto flex h-16 w-full max-w-6xl items-center justify-between px-6">
                    <Link
                        href="/"
                        className="flex items-center gap-2 font-semibold"
                    >
                        <span className="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground">
                            <AppLogoIcon className="size-4 fill-current" />
                        </span>
                        Nexus&#8209;UI
                    </Link>

                    <nav className="hidden items-center gap-6 text-sm text-muted-foreground md:flex">
                        {primaryNav.map((item) => (
                            <Link
                                key={item.href}
                                href={item.href}
                                className="transition-colors hover:text-foreground"
                            >
                                {item.label}
                            </Link>
                        ))}
                    </nav>

                    <div className="hidden items-center gap-2 md:flex">
                        {auth?.user ? (
                            <Button asChild size="sm">
                                <Link href="/dashboard">Dashboard</Link>
                            </Button>
                        ) : (
                            <>
                                <Button asChild variant="ghost" size="sm">
                                    <Link href="/login">Log in</Link>
                                </Button>
                                <Button asChild size="sm">
                                    <Link href="/register">Get started</Link>
                                </Button>
                            </>
                        )}
                    </div>

                    <button
                        type="button"
                        aria-label="Toggle menu"
                        onClick={() => setMobileOpen((o) => !o)}
                        className="inline-flex size-9 items-center justify-center rounded-md border border-border md:hidden"
                    >
                        {mobileOpen ? (
                            <X className="size-4" />
                        ) : (
                            <Menu className="size-4" />
                        )}
                    </button>
                </div>

                {mobileOpen && (
                    <div className="border-t border-border/60 md:hidden">
                        <div className="mx-auto flex w-full max-w-6xl flex-col gap-1 px-6 py-4 text-sm">
                            {primaryNav.map((item) => (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    onClick={() => setMobileOpen(false)}
                                    className="rounded-md px-2 py-2 text-muted-foreground hover:bg-muted hover:text-foreground"
                                >
                                    {item.label}
                                </Link>
                            ))}
                            <Separator className="my-2" />
                            {auth?.user ? (
                                <Button asChild>
                                    <Link href="/dashboard">Dashboard</Link>
                                </Button>
                            ) : (
                                <div className="flex gap-2">
                                    <Button
                                        asChild
                                        variant="outline"
                                        className="flex-1"
                                    >
                                        <Link href="/login">Log in</Link>
                                    </Button>
                                    <Button asChild className="flex-1">
                                        <Link href="/register">
                                            Get started
                                        </Link>
                                    </Button>
                                </div>
                            )}
                        </div>
                    </div>
                )}
            </header>

            <main className="flex-1">{children}</main>

            <footer className="border-t border-border/60 bg-muted/30">
                <div className="mx-auto w-full max-w-6xl px-6 py-12">
                    <div className="grid gap-10 md:grid-cols-4">
                        <div className="space-y-3">
                            <Link
                                href="/"
                                className="flex items-center gap-2 font-semibold"
                            >
                                <span className="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground">
                                    <AppLogoIcon className="size-4 fill-current" />
                                </span>
                                Nexus&#8209;UI
                            </Link>
                            <p className="max-w-xs text-sm text-muted-foreground">
                                A structured output sink for LLM agents.
                                Persistent, versioned, interactive workbenches
                                instead of messy chat output.
                            </p>
                        </div>
                        {footerNav.map((section) => (
                            <div key={section.heading}>
                                <h3 className="mb-3 text-sm font-semibold">
                                    {section.heading}
                                </h3>
                                <ul className="space-y-2 text-sm text-muted-foreground">
                                    {section.links.map((link) => (
                                        <li key={link.href}>
                                            <Link
                                                href={link.href}
                                                className="transition-colors hover:text-foreground"
                                            >
                                                {link.label}
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ))}
                    </div>
                    <Separator className="my-8" />
                    <div className="flex flex-col items-start justify-between gap-3 text-xs text-muted-foreground md:flex-row md:items-center">
                        <p>
                            &copy; {new Date().getFullYear()} Nexus-UI. All
                            rights reserved.
                        </p>
                        <div className="flex gap-4">
                            <Link href="/terms" className="hover:text-foreground">
                                Terms
                            </Link>
                            <Link
                                href="/privacy"
                                className="hover:text-foreground"
                            >
                                Privacy
                            </Link>
                            <Link
                                href="/cookies"
                                className="hover:text-foreground"
                            >
                                Cookies
                            </Link>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    );
}
