import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { login, register } from '@/routes';

type WelcomeProps = { canRegister?: boolean };

const NexusMark = ({ size = 26 }: { size?: number }) => (
    <svg
        width={size}
        height={size}
        viewBox="0 0 24 24"
        fill="none"
        aria-hidden="true"
    >
        <rect
            x="2"
            y="2"
            width="20"
            height="20"
            rx="5"
            stroke="var(--accent)"
            strokeWidth="1.6"
        />
        <path
            d="M7 17V7L17 17V7"
            stroke="var(--accent)"
            strokeWidth="1.8"
            strokeLinecap="round"
            strokeLinejoin="round"
        />
        <circle cx="7" cy="7" r="1.5" fill="var(--accent)" />
        <circle cx="17" cy="17" r="1.5" fill="var(--accent)" />
    </svg>
);

const CheckIcon = () => (
    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
        <path
            d="M3 8.5l3 3 7-7"
            stroke="currentColor"
            strokeWidth="1.7"
            strokeLinecap="round"
            strokeLinejoin="round"
        />
    </svg>
);

const faqItems = [
    {
        q: 'Does Nexus run my agent for me?',
        a: 'No — you run your agent wherever you want. Nexus is a place for the structured output to land, version, and be reviewed. Think of it as a filesystem your agent can write rich views to, with a URL.',
    },
    {
        q: 'Which providers does the SDK support?',
        a: "All of them. The SDK is provider-agnostic — it's just HTTP. There are first-class adapters for the OpenAI Agents SDK, Anthropic's tool API, the Vercel AI SDK, and anything that speaks MCP.",
    },
    {
        q: "What's the inline preview limit for?",
        a: 'Agents often can\u2019t fetch arbitrary URLs. The inline preview (≤64KB of self-contained HTML) lets the model "see" what it just wrote without another roundtrip — useful for self-correction loops.',
    },
    {
        q: 'How does pricing count a "snapshot"?',
        a: 'One POST that creates or updates a snapshot = one counted event. Reads and shares are free and unlimited on every plan. No surprises.',
    },
    {
        q: 'Can I self-host?',
        a: "Not yet — the hosted tier is where we're focused. A self-host distribution is on the roadmap for teams with residency needs; email us if that's you.",
    },
    {
        q: "Who's behind this?",
        a: 'A small team in Toronto and Berlin, previously at Linear, Vercel, and GitHub. We got tired of agents producing great structured output that nobody saw.',
    },
];

const TERMINAL_CODE_HTML = `<span class="tk-c">// 1. Emit a structured snapshot from your agent</span>
<span class="tk-k">import</span> { <span class="tk-f">nexus</span> } <span class="tk-k">from</span> <span class="tk-s">"nexus-sdk"</span>;

<span class="tk-k">const</span> <span class="tk-p">snap</span> = <span class="tk-k">await</span> <span class="tk-f">nexus</span>.<span class="tk-f">write</span>({
  <span class="tk-d">workbench</span>: <span class="tk-s">"incidents"</span>,
  <span class="tk-d">view</span>: <span class="tk-s">"slide_deck"</span>,
  <span class="tk-d">slides</span>: [
    { <span class="tk-d">kind</span>: <span class="tk-s">"metrics"</span>, <span class="tk-d">cards</span>: [
      { <span class="tk-d">label</span>: <span class="tk-s">"p99 peak"</span>, <span class="tk-d">value</span>: <span class="tk-s">"1,412ms"</span> },
      { <span class="tk-d">label</span>: <span class="tk-s">"failed checkouts"</span>, <span class="tk-d">value</span>: 3218 },
    ] },
  ],
});

<span class="tk-c">// 2. Hand the URL + inline preview back to your model</span>
<span class="tk-k">return</span> { <span class="tk-d">content</span>: [
  { <span class="tk-d">type</span>: <span class="tk-s">"text"</span>, <span class="tk-d">text</span>: \`Snapshot ready: \${<span class="tk-p">snap</span>.<span class="tk-p">url</span>}\` },
  { <span class="tk-d">type</span>: <span class="tk-s">"html"</span>, <span class="tk-d">html</span>: <span class="tk-p">snap</span>.<span class="tk-p">preview</span> },  <span class="tk-c">// &le; 64KB</span>
] };

<span class="tk-c">// 3. Resume the agent when a human selects something</span>
<span class="tk-p">snap</span>.<span class="tk-f">onSelection</span>(<span class="tk-k">async</span> ({ <span class="tk-p">items</span>, <span class="tk-p">revision</span> }) =&gt; {
  <span class="tk-k">await</span> <span class="tk-f">agent</span>.<span class="tk-f">continue</span>({
    <span class="tk-d">context</span>: { <span class="tk-p">items</span>, <span class="tk-p">revision</span> },
    <span class="tk-d">instruction</span>: <span class="tk-s">"Draft follow-up tasks for these."</span>,
  });
});
`;

const demoCards = [
    { id: 'm1', lbl: 'p99 peak', val: '1,412ms', dl: '+1,044ms', err: true },
    {
        id: 'm2',
        lbl: 'failed checkouts',
        val: '3,218',
        dl: '+3,218',
        err: true,
    },
    {
        id: 'm3',
        lbl: 'affected accounts',
        val: '11.2k',
        dl: '≈1.9%',
        err: false,
    },
    {
        id: 'm4',
        lbl: 'revenue risk',
        val: '$84k',
        dl: 'recoverable',
        err: false,
    },
];

export default function Welcome(_props: WelcomeProps) {
    const [openFaq, setOpenFaq] = useState<number | null>(0);
    const [selected, setSelected] = useState<Set<string>>(new Set());

    useEffect(() => {
        const root = document.documentElement;
        root.classList.add('mkt-page-root');
        return () => root.classList.remove('mkt-page-root');
    }, []);

    const toggleCard = (id: string) => {
        setSelected((prev) => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });
    };

    const selectedCount = selected.size;

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') setSelected(new Set());
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, []);

    return (
        <div className="mkt-page">
            <Head title="Nexus — structured workbenches for your agents">
                <meta
                    name="description"
                    content="Nexus gives AI agents a place to write back. Tables, decks, kanbans, and flowcharts rendered from JSON — versioned, diffable, and small enough to fit in a tool call."
                />
            </Head>

            {/* NAV */}
            <nav className="nav">
                <div className="container nav-inner">
                    <a className="brand" href="#">
                        <NexusMark />
                        <span>nexus</span>
                    </a>
                    <div className="nav-links">
                        <a href="#how">How it works</a>
                        <a href="#features">Features</a>
                        <a href="#demo">Demo</a>
                        <a href="#pricing">Pricing</a>
                        <a href="#faq">Docs</a>
                    </div>
                    <div className="nav-right">
                        <Link href={login()} className="btn sm ghost">
                            Sign in
                        </Link>
                        <Link href={register()} className="btn sm primary">
                            Get started <span className="arrow">→</span>
                        </Link>
                    </div>
                </div>
            </nav>

            {/* HERO */}
            <section className="hero">
                <div className="container">
                    <div className="hero-grid">
                        <div>
                            <div className="eyebrow">
                                <span className="pill">NEW</span>
                                <span>v2.0 · embeddable snapshots</span>
                                <span className="arrow-mini">→</span>
                            </div>
                            <h1 className="display">
                                Structured{' '}
                                <em>
                                    <span className="ink">workbenches</span>
                                </em>{' '}
                                for the agents you already run.
                            </h1>
                            <p className="hero-lede">
                                Nexus gives your AI agents a place to{' '}
                                <strong>write back</strong>. Tables, decks,
                                kanbans, and flowcharts rendered from JSON —
                                versioned, diffable, and small enough to fit in
                                a tool call.{' '}
                                <strong>
                                    Your agent emits a snapshot. Nexus turns it
                                    into something you can read.
                                </strong>
                            </p>
                            <div className="hero-cta">
                                <Link
                                    href={register()}
                                    className="btn lg primary"
                                >
                                    Start free{' '}
                                    <span className="arrow">→</span>
                                </Link>
                                <a href="#demo" className="btn lg">
                                    See the demo
                                </a>
                            </div>
                            <div className="hero-meta">
                                <span>npm i nexus-sdk</span>
                                <span className="dot">·</span>
                                <span>free for 3 workbenches</span>
                                <span className="dot">·</span>
                                <span>no credit card</span>
                            </div>
                        </div>

                        {/* HERO PREVIEW */}
                        <div className="hero-preview" aria-hidden="true">
                            <div className="hp-chrome">
                                <div className="dots">
                                    <span />
                                    <span />
                                    <span />
                                </div>
                                <div className="url">
                                    nexus.dev/w/incidents/inc-0412-postmortem ·
                                    rev 7
                                </div>
                            </div>
                            <div className="hp-body">
                                <div className="hp-nav">
                                    <div className="hp-sec">workbench</div>
                                    <div className="hp-item on">
                                        <span className="sq" />
                                        incidents
                                        <span className="tiny-count">12</span>
                                    </div>
                                    <div className="hp-item">
                                        <span className="sq" />
                                        release-gate
                                        <span className="tiny-count">4</span>
                                    </div>
                                    <div className="hp-item">
                                        <span className="sq" />
                                        cost-audit
                                        <span className="tiny-count">31</span>
                                    </div>
                                    <div className="hp-sec">snapshot</div>
                                    <div
                                        className="hp-item on"
                                        style={{ paddingLeft: 16 }}
                                    >
                                        <span className="sq" />
                                        inc-0412
                                    </div>
                                    <div
                                        className="hp-item"
                                        style={{ paddingLeft: 16 }}
                                    >
                                        <span className="sq" />
                                        open-inc
                                    </div>
                                </div>
                                <div className="hp-stage">
                                    <div className="hp-head">
                                        <span className="hp-chip">
                                            slide_deck
                                        </span>
                                        <span className="hp-title">
                                            INC-0412 · checkout latency
                                        </span>
                                    </div>
                                    <div className="hp-slide">
                                        <span className="eye">
                                            IMPACT · CUSTOMER-FACING
                                        </span>
                                        <h3>
                                            p99 latency peaked 3.4× above
                                            baseline for 47 minutes
                                        </h3>
                                        <p>
                                            Cache warmer shipped with stale
                                            keyspace; pricing-read cascaded
                                            into a burn.
                                        </p>
                                        <div className="mini-grid">
                                            <div className="hp-card sel">
                                                <span className="lbl">
                                                    p99 peak
                                                </span>
                                                <span className="val">
                                                    1,412ms
                                                </span>
                                                <span className="dl">
                                                    +1,044ms
                                                </span>
                                            </div>
                                            <div className="hp-card">
                                                <span className="lbl">
                                                    failed checkouts
                                                </span>
                                                <span className="val">
                                                    3,218
                                                </span>
                                                <span className="dl">
                                                    +3,218
                                                </span>
                                            </div>
                                            <div className="hp-card sel">
                                                <span className="lbl">
                                                    affected
                                                </span>
                                                <span className="val">
                                                    11.2k
                                                </span>
                                                <span className="dl">
                                                    ≈1.9%
                                                </span>
                                            </div>
                                            <div className="hp-card">
                                                <span className="lbl">
                                                    revenue risk
                                                </span>
                                                <span className="val">
                                                    $84k
                                                </span>
                                                <span className="dl">
                                                    recoverable
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div className="hp-foot">
                                        <span
                                            className="mono"
                                            style={{
                                                fontSize: 10,
                                                color: 'var(--fg-3)',
                                            }}
                                        >
                                            02 / 06
                                        </span>
                                        <div className="hp-dots">
                                            <span />
                                            <span className="on" />
                                            <span />
                                            <span />
                                            <span />
                                            <span />
                                        </div>
                                    </div>
                                </div>
                                <div className="hp-rail">
                                    <div
                                        style={{
                                            fontFamily: 'var(--mono)',
                                            fontSize: 10,
                                            color: 'var(--fg-3)',
                                            textTransform: 'uppercase',
                                            letterSpacing: '.08em',
                                        }}
                                    >
                                        revisions · 7
                                    </div>
                                    <div className="hp-rev cur">
                                        <div className="tk" />
                                        <div>
                                            <div className="rv">
                                                rev <b>#07</b> · just now
                                            </div>
                                            <div className="nt">
                                                Added follow-up owners +
                                                estimates.
                                            </div>
                                        </div>
                                    </div>
                                    <div className="hp-rev">
                                        <div className="tk" />
                                        <div>
                                            <div className="rv">
                                                rev <b>#06</b> · 4 min ago
                                            </div>
                                            <div className="nt">
                                                Rewrote root cause.
                                            </div>
                                        </div>
                                    </div>
                                    <div className="hp-rev">
                                        <div className="tk" />
                                        <div>
                                            <div className="rv">
                                                rev <b>#05</b> · 11 min ago
                                            </div>
                                            <div className="nt">
                                                Metrics from grafana.
                                            </div>
                                        </div>
                                    </div>
                                    <div className="hp-rev">
                                        <div className="tk" />
                                        <div>
                                            <div className="rv">
                                                rev <b>#04</b> · 22 min
                                            </div>
                                            <div className="nt">
                                                Cross-ref'd pager.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div className="hp-selbar">
                                <span className="nn">2</span>
                                <span>selected</span>
                                <span className="divider" />
                                <span className="send">↵ send to agent</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {/* LOGO BAR */}
            <section
                className="logobar"
                style={{ padding: '36px 0 44px' }}
            >
                <div className="container">
                    <div className="header">
                        Weekend projects and side-projects using nexus
                    </div>
                    <div className="logogrid">
                        {[
                            'shiplog',
                            'tinycode',
                            'zig/zag',
                            'peakd',
                            'onto.fm',
                            'stridelabs',
                        ].map((name) => (
                            <div key={name} className="lg">
                                <svg
                                    width="18"
                                    height="18"
                                    viewBox="0 0 20 20"
                                    fill="none"
                                >
                                    <circle
                                        cx="10"
                                        cy="10"
                                        r="7"
                                        stroke="currentColor"
                                        strokeWidth="1.5"
                                    />
                                    <path
                                        d="M3 10h14M10 3c2 2.5 2 11.5 0 14"
                                        stroke="currentColor"
                                        strokeWidth="1.5"
                                    />
                                </svg>
                                {name}
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* HOW IT WORKS */}
            <section id="how">
                <div className="container">
                    <div className="section-head">
                        <span className="kicker">How it works</span>
                        <h2>
                            Three lines of JSON.{' '}
                            <em>One shareable page.</em>
                        </h2>
                        <p>
                            Your agent already knows how to produce structured
                            output. Nexus gives that output a home — with
                            versions, links, and an interface humans don't
                            hate.
                        </p>
                    </div>

                    <div className="steps">
                        <div className="step">
                            <div className="ring">
                                <svg
                                    width="18"
                                    height="18"
                                    viewBox="0 0 20 20"
                                    fill="none"
                                >
                                    <path
                                        d="M3 8.5L10 4l7 4.5v6.5l-7 4-7-4V8.5z"
                                        stroke="currentColor"
                                        strokeWidth="1.5"
                                        strokeLinejoin="round"
                                    />
                                    <path
                                        d="M10 11v5M3 8.5l7 4 7-4.5"
                                        stroke="currentColor"
                                        strokeWidth="1.5"
                                        strokeLinejoin="round"
                                    />
                                </svg>
                            </div>
                            <h3>Agent emits a snapshot</h3>
                            <p>
                                POST structured JSON to a workbench. Pick a
                                view type — table, kanban, deck, flowchart —
                                Nexus handles rendering, links, and
                                pagination.
                            </p>
                            <span className="tag">POST /w/incidents</span>
                        </div>
                        <div className="step">
                            <div className="ring">
                                <svg
                                    width="18"
                                    height="18"
                                    viewBox="0 0 20 20"
                                    fill="none"
                                >
                                    <path
                                        d="M3 10a7 7 0 1011-5.5"
                                        stroke="currentColor"
                                        strokeWidth="1.5"
                                        strokeLinecap="round"
                                    />
                                    <path
                                        d="M13 2v3.5h-3.5"
                                        stroke="currentColor"
                                        strokeWidth="1.5"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    />
                                    <circle
                                        cx="10"
                                        cy="10"
                                        r="2"
                                        fill="currentColor"
                                    />
                                </svg>
                            </div>
                            <h3>Every edit becomes a revision</h3>
                            <p>
                                Agents and humans both append. Scroll the rail
                                to see what changed, compare versions, or roll
                                back to any point with one click.
                            </p>
                            <span className="tag">revisions · immutable</span>
                        </div>
                        <div className="step">
                            <div className="ring">
                                <svg
                                    width="18"
                                    height="18"
                                    viewBox="0 0 20 20"
                                    fill="none"
                                >
                                    <path
                                        d="M4 10l3 3 9-9"
                                        stroke="currentColor"
                                        strokeWidth="1.7"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    />
                                    <path
                                        d="M16 10v5a1.5 1.5 0 01-1.5 1.5h-9A1.5 1.5 0 014 15V5.5A1.5 1.5 0 015.5 4H11"
                                        stroke="currentColor"
                                        strokeWidth="1.5"
                                        strokeLinecap="round"
                                    />
                                </svg>
                            </div>
                            <h3>Humans select, agents continue</h3>
                            <p>
                                Pick any row, card, or slide-item. Send the
                                selection back to the agent as a new prompt
                                with full context. No copy-paste archaeology.
                            </p>
                            <span className="tag">⌘ + ↵</span>
                        </div>
                    </div>
                </div>
            </section>

            {/* FEATURES */}
            <section id="features" style={{ paddingTop: 24 }}>
                <div className="container">
                    <div className="section-head">
                        <span className="kicker">Features</span>
                        <h2>
                            Everything your agent's output needs —{' '}
                            <em>nothing it doesn't.</em>
                        </h2>
                    </div>

                    <div className="features">
                        <div className="feature tall">
                            <div className="ico">
                                <svg
                                    width="16"
                                    height="16"
                                    viewBox="0 0 16 16"
                                    fill="none"
                                >
                                    <rect
                                        x="2"
                                        y="3"
                                        width="12"
                                        height="10"
                                        rx="1.5"
                                        stroke="currentColor"
                                        strokeWidth="1.25"
                                    />
                                    <path
                                        d="M2 6.5h12M5 3v10"
                                        stroke="currentColor"
                                        strokeWidth="1.25"
                                    />
                                </svg>
                            </div>
                            <h3>Four view types, one payload format</h3>
                            <p>
                                Switch how the same data renders without
                                rewriting it. Table today, kanban tomorrow,
                                slide deck for the weekly share-out — your
                                agent emits the data once.
                            </p>
                            <div className="views-visual">
                                <div className="view-tile deck">
                                    <span className="t">slide_deck</span>
                                    <div className="mini">
                                        <span />
                                        <span />
                                        <span />
                                    </div>
                                </div>
                                <div className="view-tile table">
                                    <span className="t">table</span>
                                    <div className="mini">
                                        <span />
                                        <span />
                                        <span />
                                        <span />
                                        <span />
                                        <span />
                                        <span />
                                        <span />
                                        <span />
                                    </div>
                                </div>
                                <div className="view-tile kanban">
                                    <span className="t">kanban</span>
                                    <div className="mini">
                                        <span />
                                        <span />
                                        <span />
                                    </div>
                                </div>
                                <div className="view-tile flow">
                                    <span className="t">flowchart</span>
                                    <div className="mini">
                                        <span className="line" />
                                        <span className="dot" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="feature wide">
                            <div className="ico">
                                <svg
                                    width="16"
                                    height="16"
                                    viewBox="0 0 16 16"
                                    fill="none"
                                >
                                    <path
                                        d="M3 8a5 5 0 105-5H5.5M3 3v2.5H5.5"
                                        stroke="currentColor"
                                        strokeWidth="1.3"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    />
                                    <path
                                        d="M8 5.5V8l2 1.5"
                                        stroke="currentColor"
                                        strokeWidth="1.3"
                                        strokeLinecap="round"
                                    />
                                </svg>
                            </div>
                            <h3>Revisions, not replacements</h3>
                            <p>
                                Every agent write is immutable. Diff two
                                revisions, roll back, or branch a new one from
                                any point. No more "where did that version
                                go?"
                            </p>
                            <div className="revs-mini">
                                <div className="rmini-row cur">
                                    <div className="tk" />
                                    <div className="lbl">
                                        rev <b>#07</b> · just now{' '}
                                        <span
                                            className="note"
                                            style={{ color: 'var(--fg-0)' }}
                                        >
                                            Added follow-up owners +
                                            estimates.
                                        </span>
                                    </div>
                                    <span className="time">+6 −0</span>
                                </div>
                                <div className="rmini-row">
                                    <div className="tk" />
                                    <div className="lbl">
                                        rev <b>#06</b> · 4 min{' '}
                                        <span className="note">
                                            Rewrote root cause for clarity.
                                        </span>
                                    </div>
                                    <span className="time">+12 −9</span>
                                </div>
                                <div className="rmini-row">
                                    <div className="tk" />
                                    <div className="lbl">
                                        rev <b>#05</b> · 11 min{' '}
                                        <span className="note">
                                            Impact metrics from grafana
                                            export.
                                        </span>
                                    </div>
                                    <span className="time">+4 −0</span>
                                </div>
                            </div>
                        </div>

                        <div className="feature">
                            <div className="ico">
                                <svg
                                    width="16"
                                    height="16"
                                    viewBox="0 0 16 16"
                                    fill="none"
                                >
                                    <path
                                        d="M14 2L2 7.5l4.5 1.5M14 2L8.5 14l-2-5L14 2z"
                                        stroke="currentColor"
                                        strokeWidth="1.25"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    />
                                </svg>
                            </div>
                            <h3>Selections become prompts</h3>
                            <p>
                                Click anything. Send it back as structured
                                context. The agent gets exactly the rows you
                                meant — no prose translation.
                            </p>
                        </div>

                        <div className="feature">
                            <div className="ico">
                                <svg
                                    width="16"
                                    height="16"
                                    viewBox="0 0 16 16"
                                    fill="none"
                                >
                                    <rect
                                        x="2"
                                        y="3"
                                        width="12"
                                        height="10"
                                        rx="1.5"
                                        stroke="currentColor"
                                        strokeWidth="1.25"
                                    />
                                    <circle
                                        cx="5.5"
                                        cy="7.5"
                                        r="1"
                                        fill="currentColor"
                                    />
                                    <path
                                        d="M8 7l3 3 2-2"
                                        stroke="currentColor"
                                        strokeWidth="1.25"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    />
                                </svg>
                            </div>
                            <h3>Inline preview ≤ 64KB</h3>
                            <p>
                                Agents get a full HTML preview inline in their
                                tool response — no second API roundtrip needed
                                to "see" what they wrote.
                            </p>
                        </div>

                        <div className="feature">
                            <div className="ico">
                                <svg
                                    width="16"
                                    height="16"
                                    viewBox="0 0 16 16"
                                    fill="none"
                                >
                                    <rect
                                        x="2"
                                        y="4"
                                        width="12"
                                        height="8"
                                        rx="1.5"
                                        stroke="currentColor"
                                        strokeWidth="1.25"
                                    />
                                    <path
                                        d="M4 6.5h0.01M6.5 6.5h0.01M9 6.5h0.01M11.5 6.5h0.01M5 9.5h6"
                                        stroke="currentColor"
                                        strokeWidth="1.25"
                                        strokeLinecap="round"
                                    />
                                </svg>
                            </div>
                            <h3>Keyboard first</h3>
                            <p>
                                Every surface has a shortcut. Arrow-navigate,{' '}
                                <kbd>x</kbd> to select, <kbd>⌘</kbd>
                                <kbd>↵</kbd> to send. Your fingers never leave
                                home row.
                            </p>
                        </div>

                        <div className="feature">
                            <div className="ico">
                                <svg
                                    width="16"
                                    height="16"
                                    viewBox="0 0 16 16"
                                    fill="none"
                                >
                                    <circle
                                        cx="5"
                                        cy="8"
                                        r="1.75"
                                        stroke="currentColor"
                                        strokeWidth="1.25"
                                    />
                                    <circle
                                        cx="11"
                                        cy="4"
                                        r="1.75"
                                        stroke="currentColor"
                                        strokeWidth="1.25"
                                    />
                                    <circle
                                        cx="11"
                                        cy="12"
                                        r="1.75"
                                        stroke="currentColor"
                                        strokeWidth="1.25"
                                    />
                                    <path
                                        d="M6.6 7.3l2.8-1.6M6.6 8.7l2.8 1.6"
                                        stroke="currentColor"
                                        strokeWidth="1.25"
                                    />
                                </svg>
                            </div>
                            <h3>Share by URL</h3>
                            <p>
                                Public, password, or team-scoped. Every
                                snapshot is a link. Every revision has a
                                permalink.
                            </p>
                        </div>

                        <div className="feature">
                            <div className="ico">
                                <svg
                                    width="16"
                                    height="16"
                                    viewBox="0 0 16 16"
                                    fill="none"
                                >
                                    <circle
                                        cx="8"
                                        cy="5"
                                        r="2.5"
                                        stroke="currentColor"
                                        strokeWidth="1.25"
                                    />
                                    <path
                                        d="M3 14a5 5 0 0110 0"
                                        stroke="currentColor"
                                        strokeWidth="1.25"
                                        strokeLinecap="round"
                                    />
                                </svg>
                            </div>
                            <h3>Bring your own agent</h3>
                            <p>
                                OpenAI, Anthropic, local — Nexus is
                                transport-agnostic. There's an MCP server, a
                                REST API, and a one-line SDK.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            {/* DEMO */}
            <section id="demo" className="demo">
                <div className="container">
                    <div className="section-head">
                        <span className="kicker">Interactive demo</span>
                        <h2>
                            Select any card. <em>Send it back.</em>
                        </h2>
                        <p>
                            This is a real snapshot. Click the metric cards to
                            select — watch the selection bar populate. Press{' '}
                            <kbd>⌘</kbd>
                            <kbd>↵</kbd> or the button to send.
                        </p>
                    </div>

                    <div className="demo-shell">
                        <div className="demo-head">
                            <div className="dots">
                                <span />
                                <span />
                                <span />
                            </div>
                            <div className="title">
                                nexus · <b>inc-0412-postmortem</b> · rev 7
                            </div>
                            <div className="demo-tabs">
                                <button className="demo-tab on">Deck</button>
                                <button className="demo-tab">Table</button>
                                <button className="demo-tab">JSON</button>
                            </div>
                        </div>
                        <div className="demo-body">
                            <aside className="demo-side">
                                <div className="sec">Slides</div>
                                <div className="it">
                                    <span className="sq" />
                                    Title<span className="c">01</span>
                                </div>
                                <div className="it">
                                    <span className="sq" />
                                    Timeline<span className="c">02</span>
                                </div>
                                <div className="it on">
                                    <span className="sq" />
                                    Impact<span className="c">03</span>
                                </div>
                                <div className="it">
                                    <span className="sq" />
                                    Root cause<span className="c">04</span>
                                </div>
                                <div className="it">
                                    <span className="sq" />
                                    Follow-ups<span className="c">05</span>
                                </div>
                                <div className="it">
                                    <span className="sq" />
                                    Closing<span className="c">06</span>
                                </div>
                                <div className="sec">Revision</div>
                                <div className="it on">
                                    <span className="sq" />
                                    #07 (current)
                                </div>
                                <div className="it">
                                    <span className="sq" />
                                    #06 · 4 min ago
                                </div>
                                <div className="it">
                                    <span className="sq" />
                                    #05 · 11 min ago
                                </div>
                            </aside>
                            <div className="demo-view">
                                <div className="chip-row">
                                    <span className="hp-chip">slide_deck</span>
                                    <h3>Impact — customer-facing</h3>
                                </div>
                                <p className="lede">
                                    p99 latency peaked 3.4× above baseline for
                                    47 minutes. Cache warmer shipped with a
                                    stale keyspace.
                                </p>
                                <div className="grid4">
                                    {demoCards.map((c) => {
                                        const isSel = selected.has(c.id);
                                        return (
                                            <button
                                                key={c.id}
                                                type="button"
                                                className={`demo-card${isSel ? ' sel' : ''}`}
                                                onClick={() => toggleCard(c.id)}
                                            >
                                                <div className="tick">
                                                    <svg
                                                        className="icon"
                                                        width="10"
                                                        height="10"
                                                        viewBox="0 0 16 16"
                                                        fill="none"
                                                    >
                                                        <path
                                                            d="M3 8.5l3 3 7-7"
                                                            stroke="currentColor"
                                                            strokeWidth="1.8"
                                                            strokeLinecap="round"
                                                            strokeLinejoin="round"
                                                        />
                                                    </svg>
                                                </div>
                                                <span className="lbl">
                                                    {c.lbl}
                                                </span>
                                                <span className="val">
                                                    {c.val}
                                                </span>
                                                <span
                                                    className={`dl${c.err ? ' err' : ''}`}
                                                >
                                                    {c.dl}
                                                </span>
                                            </button>
                                        );
                                    })}
                                </div>

                                <div
                                    className={`demo-selbar${selectedCount === 0 ? ' hidden' : ''}`}
                                >
                                    <span className="n">{selectedCount}</span>
                                    <span className="txt">
                                        {selectedCount === 1
                                            ? 'selected'
                                            : 'selected'}
                                    </span>
                                    <span className="spacer" />
                                    <button
                                        type="button"
                                        className="clear"
                                        onClick={() => setSelected(new Set())}
                                    >
                                        Clear
                                    </button>
                                    <button type="button" className="send">
                                        <svg
                                            width="12"
                                            height="12"
                                            viewBox="0 0 16 16"
                                            fill="none"
                                        >
                                            <path
                                                d="M14 2L2 7.5l4.5 1.5M14 2L8.5 14l-2-5L14 2z"
                                                stroke="currentColor"
                                                strokeWidth="1.4"
                                                strokeLinecap="round"
                                                strokeLinejoin="round"
                                            />
                                        </svg>
                                        Send back to agent
                                    </button>
                                </div>

                                <div className="demo-hint">
                                    <span>Try it:</span>
                                    <span>click cards</span>
                                    <span>·</span>
                                    <span>
                                        press <kbd>⌘</kbd>
                                        <kbd>↵</kbd> to send
                                    </span>
                                    <span>·</span>
                                    <span>
                                        press <kbd>esc</kbd> to clear
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {/* CODE */}
            <section className="code-section">
                <div className="container">
                    <div className="section-head">
                        <span className="kicker">Integrate in 60 seconds</span>
                        <h2>
                            No new mental model.{' '}
                            <em>Just return a snapshot.</em>
                        </h2>
                        <p>
                            One import, one function call. Works with any
                            agent framework, any model provider.
                        </p>
                    </div>

                    <div className="code-grid">
                        <div className="code-points">
                            <div className="pt">
                                <h4>
                                    <span className="num">01</span> One
                                    dependency
                                </h4>
                                <p>
                                    Install{' '}
                                    <code className="mono">nexus-sdk</code>.
                                    It's 28KB. No peer deps. Works in Node,
                                    Bun, Deno, and edge runtimes.
                                </p>
                            </div>
                            <div className="pt">
                                <h4>
                                    <span className="num">02</span> One API
                                    call
                                </h4>
                                <p>
                                    Pass a workbench slug and a typed payload.
                                    Nexus returns a URL and an inline preview
                                    your agent can read.
                                </p>
                            </div>
                            <div className="pt">
                                <h4>
                                    <span className="num">03</span> One
                                    selection hook
                                </h4>
                                <p>
                                    Subscribe to{' '}
                                    <code className="mono">
                                        snapshot.onSelection
                                    </code>{' '}
                                    to receive the cards the human picked,
                                    typed and ready to pipe into your next
                                    prompt.
                                </p>
                            </div>
                        </div>

                        <div className="terminal">
                            <div className="bar">
                                <div className="dots">
                                    <span />
                                    <span />
                                    <span />
                                </div>
                                <span className="title">incident-bot.ts</span>
                                <div className="pills">
                                    <span className="pill">TypeScript</span>
                                </div>
                            </div>
                            <pre
                                dangerouslySetInnerHTML={{
                                    __html: TERMINAL_CODE_HTML,
                                }}
                            />
                        </div>
                    </div>
                </div>
            </section>

            {/* PRICING */}
            <section id="pricing">
                <div className="container">
                    <div className="section-head">
                        <span className="kicker">Pricing</span>
                        <h2>
                            Free to start. <em>Honest when you grow.</em>
                        </h2>
                        <p>
                            No seats. No per-call gotchas. Priced on the
                            number of workbenches and storage — the things you
                            can actually feel.
                        </p>
                    </div>

                    <div className="pricing-grid">
                        <div className="plan">
                            <span className="name">Hobby</span>
                            <div className="price">
                                <span className="n">$0</span>
                                <span className="per">forever</span>
                            </div>
                            <span className="tag">
                                For weekend projects and agent experiments.
                            </span>
                            <ul>
                                {[
                                    '3 workbenches',
                                    '50 snapshots / month',
                                    'All four view types',
                                    'Public share links',
                                    '7-day revision history',
                                ].map((f) => (
                                    <li key={f}>
                                        <CheckIcon />
                                        {f}
                                    </li>
                                ))}
                            </ul>
                            <div className="cta">
                                <Link href={register()} className="btn">
                                    Start free
                                </Link>
                            </div>
                        </div>

                        <div className="plan featured">
                            <span className="badge">Popular</span>
                            <span className="name">Indie</span>
                            <div className="price">
                                <span className="n">$12</span>
                                <span className="per">/ month</span>
                            </div>
                            <span className="tag">
                                For the side project that's getting real.
                            </span>
                            <ul>
                                {[
                                    'Unlimited workbenches',
                                    '5,000 snapshots / month',
                                    'Password-protected shares',
                                    'Unlimited revisions',
                                    'Custom domain',
                                    'MCP + REST + SDK',
                                ].map((f) => (
                                    <li key={f}>
                                        <CheckIcon />
                                        {f}
                                    </li>
                                ))}
                            </ul>
                            <div className="cta">
                                <Link
                                    href={register()}
                                    className="btn primary"
                                >
                                    Start 14-day trial{' '}
                                    <span className="arrow">→</span>
                                </Link>
                            </div>
                        </div>

                        <div className="plan">
                            <span className="name">Team</span>
                            <div className="price">
                                <span className="n">$48</span>
                                <span className="per">/ month</span>
                            </div>
                            <span className="tag">
                                Small teams sharing one agent stack.
                            </span>
                            <ul>
                                {[
                                    'Everything in Indie',
                                    '50,000 snapshots / month',
                                    'Team-scoped shares + SSO',
                                    'Audit log + agent attribution',
                                    'Priority support',
                                ].map((f) => (
                                    <li key={f}>
                                        <CheckIcon />
                                        {f}
                                    </li>
                                ))}
                            </ul>
                            <div className="cta">
                                <Link href={register()} className="btn">
                                    Upgrade to Team
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {/* FAQ */}
            <section id="faq">
                <div className="container">
                    <div
                        className="section-head"
                        style={{
                            textAlign: 'center',
                            marginInline: 'auto',
                            alignItems: 'center',
                        }}
                    >
                        <span className="kicker">FAQ</span>
                        <h2>
                            Short answers.{' '}
                            <em>Long enough only when it matters.</em>
                        </h2>
                    </div>

                    <div className="faq-list">
                        {faqItems.map((item, i) => {
                            const open = openFaq === i;
                            return (
                                <div
                                    key={item.q}
                                    className={`faq${open ? ' open' : ''}`}
                                >
                                    <button
                                        type="button"
                                        className="faq-q"
                                        onClick={() =>
                                            setOpenFaq(open ? null : i)
                                        }
                                    >
                                        {item.q}
                                        <span className="plus" />
                                    </button>
                                    <div className="faq-a">
                                        <div>{item.a}</div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </section>

            {/* CTA BANNER */}
            <section className="cta-banner" id="signup">
                <div className="container">
                    <h2>
                        Give your agents a <em>home page.</em>
                    </h2>
                    <p>
                        Free forever plan. 90-second install. No credit card,
                        no sales call, no dark patterns.
                    </p>
                    <div
                        style={{
                            display: 'flex',
                            gap: 10,
                            flexWrap: 'wrap',
                        }}
                    >
                        <Link href={register()} className="btn lg primary">
                            Create a workbench{' '}
                            <span className="arrow">→</span>
                        </Link>
                        <a href="#how" className="btn lg">
                            Read the docs
                        </a>
                    </div>
                </div>
            </section>

            {/* FOOTER */}
            <footer>
                <div className="container">
                    <div className="foot-grid">
                        <div className="foot-col foot-brand">
                            <a className="brand" href="#">
                                <NexusMark size={22} />
                                <span>nexus</span>
                            </a>
                            <p>
                                Structured workbenches for the agents you
                                already run.
                            </p>
                            <form
                                className="newsletter"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    const form =
                                        e.currentTarget as HTMLFormElement;
                                    const input =
                                        form.querySelector('input');
                                    const button =
                                        form.querySelector('button');
                                    if (input) input.value = '';
                                    if (button)
                                        button.textContent = '✓ subscribed';
                                }}
                            >
                                <input
                                    type="email"
                                    placeholder="you@domain.dev"
                                    required
                                />
                                <button
                                    type="submit"
                                    className="btn sm primary"
                                >
                                    Subscribe
                                </button>
                            </form>
                        </div>

                        <div className="foot-col">
                            <h5>Product</h5>
                            <ul>
                                <li>
                                    <a href="#features">Features</a>
                                </li>
                                <li>
                                    <a href="#demo">Demo</a>
                                </li>
                                <li>
                                    <a href="#pricing">Pricing</a>
                                </li>
                                <li>
                                    <a href="#">Changelog</a>
                                </li>
                                <li>
                                    <a href="#">Roadmap</a>
                                </li>
                            </ul>
                        </div>

                        <div className="foot-col">
                            <h5>Developers</h5>
                            <ul>
                                <li>
                                    <a href="#">Docs</a>
                                </li>
                                <li>
                                    <a href="#">SDK reference</a>
                                </li>
                                <li>
                                    <a href="#">MCP server</a>
                                </li>
                                <li>
                                    <a href="#">Examples</a>
                                </li>
                                <li>
                                    <a href="#">Status</a>
                                </li>
                            </ul>
                        </div>

                        <div className="foot-col">
                            <h5>Company</h5>
                            <ul>
                                <li>
                                    <a href="#">About</a>
                                </li>
                                <li>
                                    <a href="#">Blog</a>
                                </li>
                                <li>
                                    <a href="#">Contact</a>
                                </li>
                                <li>
                                    <a href="/privacy">Privacy</a>
                                </li>
                                <li>
                                    <a href="/terms">Terms</a>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div className="foot-bottom">
                        <span>
                            © 2026 nexus labs · made with care in TOR / BER
                        </span>
                        <div className="social">
                            <a href="#" aria-label="GitHub">
                                github
                            </a>
                            <a href="#" aria-label="X">
                                x
                            </a>
                            <a href="#" aria-label="Discord">
                                discord
                            </a>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    );
}

Welcome.layout = (page: React.ReactNode) => page;
