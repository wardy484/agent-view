import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    Boxes,
    CheckCircle2,
    GitBranch,
    LayoutGrid,
    LineChart,
    ShieldCheck,
    Sparkles,
    Workflow,
    Zap,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type WelcomeProps = { canRegister?: boolean };

const features = [
    {
        icon: LayoutGrid,
        title: 'Rich view types',
        body: 'Render tables, kanban boards, timelines, and diffs — not just markdown. Each view is interactive and filterable.',
    },
    {
        icon: GitBranch,
        title: 'Versioned snapshots',
        body: 'Every call appends a revision. Jump between versions to see exactly what the agent was looking at.',
    },
    {
        icon: Workflow,
        title: 'Selections as context',
        body: 'Pick rows, cards or regions, send the selection straight back to the agent as structured follow-up.',
    },
    {
        icon: ShieldCheck,
        title: 'Scoped tokens',
        body: 'Sanctum tokens scoped to a workbench keep production data out of anonymous agent traffic.',
    },
    {
        icon: Zap,
        title: 'Built on Laravel + Inertia',
        body: 'Postgres, Redis, and React 19. Deploy to Laravel Cloud with zero surprises.',
    },
    {
        icon: LineChart,
        title: 'Observable by default',
        body: 'Request logs, revision history and preview caches are all inspectable out of the box.',
    },
];

const steps = [
    {
        title: 'Mint a token',
        body: 'Create a Sanctum token in Settings and scope it to a workbench slug.',
    },
    {
        title: 'Agent calls the MCP tool',
        body: 'Your agent sends view_type + data_payload to present_structured_data.',
    },
    {
        title: 'Open the workbench',
        body: 'The tool responds with a URL. Open it — or share it with a teammate.',
    },
    {
        title: 'Send a selection back',
        body: 'Highlight what matters and pipe the selection back as follow-up context.',
    },
];

const plans = [
    {
        name: 'Hobby',
        price: '$0',
        cadence: 'forever',
        description:
            'For personal agents, weekend experiments, and trying Nexus-UI.',
        features: [
            '1 workbench',
            '500 snapshots / month',
            '7-day history',
            'Community support',
        ],
        cta: 'Start free',
        href: '/register',
        highlight: false,
    },
    {
        name: 'Team',
        price: '$29',
        cadence: 'per user / month',
        description:
            'For squads wiring real agent workflows into the daily loop.',
        features: [
            'Unlimited workbenches',
            '50k snapshots / month',
            '90-day history',
            'SSO + audit log',
            'Priority support',
        ],
        cta: 'Start 14-day trial',
        href: '/register',
        highlight: true,
    },
    {
        name: 'Enterprise',
        price: 'Custom',
        cadence: 'talk to us',
        description:
            'VPC deploys, custom retention and procurement paperwork.',
        features: [
            'Self-hosted option',
            'Custom retention',
            'DPA + security review',
            'Dedicated support',
        ],
        cta: 'Contact sales',
        href: '/contact',
        highlight: false,
    },
];

export default function Welcome({ canRegister = true }: WelcomeProps) {
    return (
        <>
            <Head title="Structured output for LLM agents">
                <meta
                    name="description"
                    content="Nexus-UI is a persistent, versioned, interactive workbench for LLM agents. Replace messy chat output with real UI."
                />
            </Head>

            {/* Hero */}
            <section className="relative overflow-hidden border-b border-border/60">
                <div className="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top,_theme(colors.primary/8%),_transparent_60%)]" />
                <div className="mx-auto w-full max-w-6xl px-6 py-20 md:py-28">
                    <div className="mx-auto max-w-3xl text-center">
                        <Badge
                            variant="secondary"
                            className="mb-6 gap-1.5 rounded-full"
                        >
                            <Sparkles className="size-3.5" />
                            MCP structured output, done right
                        </Badge>
                        <h1 className="text-4xl font-semibold tracking-tight text-balance sm:text-5xl md:text-6xl">
                            Stop pasting tables into chat.
                        </h1>
                        <p className="mt-6 text-lg text-muted-foreground text-balance sm:text-xl">
                            Nexus-UI is the structured output sink for LLM
                            agents. Agents push data to an MCP tool; users get
                            a persistent, versioned, interactive workbench and
                            can send selections right back as follow-up
                            context.
                        </p>
                        <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                            {canRegister && (
                                <Button asChild size="lg" className="gap-2">
                                    <Link href="/register">
                                        Get started free
                                        <ArrowRight className="size-4" />
                                    </Link>
                                </Button>
                            )}
                            <Button
                                asChild
                                size="lg"
                                variant="outline"
                                className="gap-2"
                            >
                                <Link href="#how-it-works">
                                    See how it works
                                </Link>
                            </Button>
                        </div>
                        <p className="mt-4 text-xs text-muted-foreground">
                            Open source core · Self-host or use our cloud ·
                            MCP-native
                        </p>
                    </div>

                    <div
                        id="product"
                        className="mt-16 rounded-xl border border-border/60 bg-card shadow-sm"
                    >
                        <div className="flex items-center gap-2 border-b border-border/60 px-4 py-2 text-xs text-muted-foreground">
                            <span className="size-2.5 rounded-full bg-red-400/80" />
                            <span className="size-2.5 rounded-full bg-yellow-400/80" />
                            <span className="size-2.5 rounded-full bg-green-400/80" />
                            <span className="ml-3 font-mono">
                                nexus-ui / workbench / rev-4
                            </span>
                        </div>
                        <div className="grid gap-0 md:grid-cols-[200px_1fr]">
                            <aside className="hidden border-r border-border/60 p-4 text-xs md:block">
                                <p className="mb-2 font-medium">Snapshots</p>
                                <ul className="space-y-1 text-muted-foreground">
                                    <li className="rounded bg-muted px-2 py-1 font-mono text-foreground">
                                        rev-4 · 2m ago
                                    </li>
                                    <li className="rounded px-2 py-1 font-mono">
                                        rev-3 · 4m ago
                                    </li>
                                    <li className="rounded px-2 py-1 font-mono">
                                        rev-2 · 9m ago
                                    </li>
                                    <li className="rounded px-2 py-1 font-mono">
                                        rev-1 · 11m ago
                                    </li>
                                </ul>
                            </aside>
                            <div className="p-5">
                                <div className="mb-3 flex items-center justify-between text-xs text-muted-foreground">
                                    <span>
                                        Showing 4 of 128 failing test runs
                                    </span>
                                    <span>Updated 2m ago</span>
                                </div>
                                <div className="overflow-hidden rounded-md border border-border/60">
                                    <table className="w-full text-left text-sm">
                                        <thead className="bg-muted/50 text-xs text-muted-foreground">
                                            <tr>
                                                <th className="px-3 py-2 font-medium">
                                                    Test
                                                </th>
                                                <th className="px-3 py-2 font-medium">
                                                    Suite
                                                </th>
                                                <th className="px-3 py-2 font-medium">
                                                    Duration
                                                </th>
                                                <th className="px-3 py-2 font-medium">
                                                    Status
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-border/60">
                                            {[
                                                [
                                                    'it renders a table',
                                                    'TableView',
                                                    '42ms',
                                                    'fail',
                                                ],
                                                [
                                                    'it renders a kanban',
                                                    'KanbanView',
                                                    '31ms',
                                                    'fail',
                                                ],
                                                [
                                                    'it appends versions',
                                                    'SnapshotVersioning',
                                                    '18ms',
                                                    'pass',
                                                ],
                                                [
                                                    'it caches preview',
                                                    'TablePreview',
                                                    '9ms',
                                                    'pass',
                                                ],
                                            ].map(
                                                ([t, suite, dur, status]) => (
                                                    <tr key={t}>
                                                        <td className="px-3 py-2 font-mono text-xs">
                                                            {t}
                                                        </td>
                                                        <td className="px-3 py-2 text-muted-foreground">
                                                            {suite}
                                                        </td>
                                                        <td className="px-3 py-2 text-muted-foreground">
                                                            {dur}
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            <Badge
                                                                variant={
                                                                    status ===
                                                                    'pass'
                                                                        ? 'secondary'
                                                                        : 'destructive'
                                                                }
                                                                className="font-mono"
                                                            >
                                                                {status}
                                                            </Badge>
                                                        </td>
                                                    </tr>
                                                ),
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {/* Features */}
            <section className="border-b border-border/60">
                <div className="mx-auto w-full max-w-6xl px-6 py-20">
                    <div className="mx-auto max-w-2xl text-center">
                        <h2 className="text-3xl font-semibold tracking-tight sm:text-4xl">
                            The output layer your agents were missing.
                        </h2>
                        <p className="mt-4 text-muted-foreground">
                            Everything you need to turn raw agent output into
                            something a human can actually use, trust, and
                            act on.
                        </p>
                    </div>
                    <div className="mt-12 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                        {features.map(({ icon: Icon, title, body }) => (
                            <Card key={title} className="border-border/60">
                                <CardHeader>
                                    <div className="mb-3 flex size-10 items-center justify-center rounded-md bg-primary/10 text-primary">
                                        <Icon className="size-5" />
                                    </div>
                                    <CardTitle className="text-lg">
                                        {title}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <CardDescription className="text-sm">
                                        {body}
                                    </CardDescription>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                </div>
            </section>

            {/* How it works */}
            <section
                id="how-it-works"
                className="border-b border-border/60 bg-muted/30"
            >
                <div className="mx-auto w-full max-w-6xl px-6 py-20">
                    <div className="mx-auto max-w-2xl text-center">
                        <h2 className="text-3xl font-semibold tracking-tight sm:text-4xl">
                            From tool call to workbench in seconds.
                        </h2>
                        <p className="mt-4 text-muted-foreground">
                            Four steps. No plumbing. Ship it to your agent
                            today.
                        </p>
                    </div>
                    <ol className="mt-12 grid gap-6 md:grid-cols-4">
                        {steps.map((step, i) => (
                            <li
                                key={step.title}
                                className="relative rounded-lg border border-border/60 bg-card p-6"
                            >
                                <span className="flex size-8 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground">
                                    {i + 1}
                                </span>
                                <h3 className="mt-4 font-semibold">
                                    {step.title}
                                </h3>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    {step.body}
                                </p>
                            </li>
                        ))}
                    </ol>
                </div>
            </section>

            {/* Pricing */}
            <section id="pricing" className="border-b border-border/60">
                <div className="mx-auto w-full max-w-6xl px-6 py-20">
                    <div className="mx-auto max-w-2xl text-center">
                        <h2 className="text-3xl font-semibold tracking-tight sm:text-4xl">
                            Simple, transparent pricing.
                        </h2>
                        <p className="mt-4 text-muted-foreground">
                            Start free. Upgrade when your agents go to work.
                        </p>
                    </div>
                    <div className="mt-12 grid gap-6 md:grid-cols-3">
                        {plans.map((plan) => (
                            <Card
                                key={plan.name}
                                className={
                                    plan.highlight
                                        ? 'relative border-primary shadow-md'
                                        : 'border-border/60'
                                }
                            >
                                {plan.highlight && (
                                    <Badge className="absolute -top-3 left-6">
                                        Most popular
                                    </Badge>
                                )}
                                <CardHeader>
                                    <CardTitle>{plan.name}</CardTitle>
                                    <CardDescription>
                                        {plan.description}
                                    </CardDescription>
                                    <div className="mt-4 flex items-baseline gap-2">
                                        <span className="text-4xl font-semibold tracking-tight">
                                            {plan.price}
                                        </span>
                                        <span className="text-sm text-muted-foreground">
                                            {plan.cadence}
                                        </span>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <ul className="space-y-2 text-sm">
                                        {plan.features.map((f) => (
                                            <li
                                                key={f}
                                                className="flex items-start gap-2"
                                            >
                                                <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-primary" />
                                                <span>{f}</span>
                                            </li>
                                        ))}
                                    </ul>
                                    <Button
                                        asChild
                                        className="w-full"
                                        variant={
                                            plan.highlight
                                                ? 'default'
                                                : 'outline'
                                        }
                                    >
                                        <Link href={plan.href}>
                                            {plan.cta}
                                        </Link>
                                    </Button>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                </div>
            </section>

            {/* CTA */}
            <section className="relative overflow-hidden">
                <div className="mx-auto w-full max-w-6xl px-6 py-20">
                    <div className="rounded-2xl border border-border/60 bg-card p-10 text-center shadow-sm">
                        <div className="mx-auto mb-4 flex size-12 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <Boxes className="size-6" />
                        </div>
                        <h2 className="text-3xl font-semibold tracking-tight sm:text-4xl">
                            Give your agents a real UI.
                        </h2>
                        <p className="mx-auto mt-4 max-w-xl text-muted-foreground">
                            Set up a workbench in under 10 minutes. Your
                            future self — scrolling through a 2k-row table
                            pasted into chat — will thank you.
                        </p>
                        <div className="mt-6 flex flex-col items-center justify-center gap-3 sm:flex-row">
                            {canRegister && (
                                <Button asChild size="lg">
                                    <Link href="/register">
                                        Create your account
                                    </Link>
                                </Button>
                            )}
                            <Button asChild size="lg" variant="outline">
                                <Link href="/contact">Talk to us</Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </section>
        </>
    );
}
