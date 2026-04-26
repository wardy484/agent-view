<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Snapshot;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use Illuminate\Database\Seeder;

/**
 * Seeds one example snapshot per view_type (slide_deck, table, kanban, flowchart)
 * under a shared "demo" workbench so the dashboard view-type cards have real
 * destinations to link to on a fresh environment.
 *
 * Idempotent — safe to re-run. Uses firstOrCreate to avoid duplicating rows,
 * and only appends a new revision when the snapshot was just created.
 */
class DemoSnapshotSeeder extends Seeder
{
    public function run(): void
    {
        $workbench = Workbench::query()->firstOrCreate(
            ['slug' => 'demo'],
            ['name' => 'Demo workbench'],
        );

        foreach ($this->samples() as $sample) {
            $snapshot = Snapshot::query()->firstOrCreate(
                [
                    'workbench_id' => $workbench->id,
                    'slug' => $sample['slug'],
                ],
                ['title' => $sample['title']],
            );

            if ($snapshot->wasRecentlyCreated) {
                SnapshotVersioning::append(
                    snapshot: $snapshot,
                    viewType: $sample['view_type'],
                    dataPayload: $sample['payload'],
                );
            }
        }
    }

    /**
     * @return array<int, array{slug: string, title: string, view_type: string, payload: array<string, mixed>}>
     */
    private function samples(): array
    {
        return [
            [
                'slug' => 'q4-plan-deck',
                'title' => 'Q4 plan deck',
                'view_type' => 'slide_deck',
                'payload' => [
                    'slides' => [
                        [
                            'title' => 'Q4 at a glance',
                            'body_md' => "- Three bets: retention, enterprise, pricing\n- $1.2M pipeline carried from Q3\n- Shipping the nexus workbench in week 2",
                        ],
                        [
                            'title' => 'Retention',
                            'body_md' => "- Churn down 1.8pp MoM\n- Onboarding rewrite ships Nov 14\n- Paid expansion holding at 112% NRR",
                        ],
                        [
                            'title' => 'Risks',
                            'body_md' => "- EU data residency review pending\n- One support lead parental leave\n- CS tooling cutover slip to Q1",
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'active-incidents',
                'title' => 'Active incidents',
                'view_type' => 'table',
                'payload' => [
                    'columns' => [
                        ['key' => 'id', 'label' => 'ID'],
                        ['key' => 'service', 'label' => 'Service'],
                        ['key' => 'severity', 'label' => 'Sev'],
                        ['key' => 'owner', 'label' => 'Owner'],
                        ['key' => 'opened_at', 'label' => 'Opened'],
                    ],
                    'rows' => [
                        ['id' => 'INC-401', 'service' => 'checkout-api', 'severity' => 'SEV-2', 'owner' => 'j.chen', 'opened_at' => '2026-04-19 08:12'],
                        ['id' => 'INC-402', 'service' => 'search', 'severity' => 'SEV-3', 'owner' => 'r.patel', 'opened_at' => '2026-04-19 11:44'],
                        ['id' => 'INC-403', 'service' => 'auth', 'severity' => 'SEV-1', 'owner' => 'm.oduya', 'opened_at' => '2026-04-20 02:07'],
                        ['id' => 'INC-404', 'service' => 'billing-worker', 'severity' => 'SEV-3', 'owner' => 's.novak', 'opened_at' => '2026-04-20 09:30'],
                    ],
                ],
            ],
            [
                'slug' => 'sprint-board',
                'title' => 'Sprint board',
                'view_type' => 'kanban',
                'payload' => [
                    'columns' => [
                        ['key' => 'todo', 'label' => 'To do'],
                        ['key' => 'doing', 'label' => 'In progress'],
                        ['key' => 'review', 'label' => 'In review'],
                        ['key' => 'done', 'label' => 'Done'],
                    ],
                    'cards' => [
                        ['id' => 'T-101', 'column_key' => 'todo', 'title' => 'Wire Stripe retry queue', 'body' => 'follow-up from INC-388 post-mortem'],
                        ['id' => 'T-102', 'column_key' => 'todo', 'title' => 'Token scope audit', 'body' => 'list all active sanctum abilities'],
                        ['id' => 'T-103', 'column_key' => 'doing', 'title' => 'Nexus view cards', 'body' => 'link each card to its latest sample'],
                        ['id' => 'T-104', 'column_key' => 'review', 'title' => 'Marketing landing rewrite', 'body' => 'dupe header/footer fix up for review'],
                        ['id' => 'T-105', 'column_key' => 'done', 'title' => 'Ship dashboard KPIs', 'body' => 'real counts from workbenches/snapshots'],
                    ],
                ],
            ],
            [
                'slug' => 'agent-flow',
                'title' => 'Agent request flow',
                'view_type' => 'flowchart',
                'payload' => [
                    'mermaid_source' => "flowchart TD\n    A[Agent] -->|POST /ai/mcp/nexus| B(MCP endpoint)\n    B --> C{view_type?}\n    C -->|table| D[TableViewSchema]\n    C -->|kanban| E[KanbanViewSchema]\n    C -->|slide_deck| F[SlideDeckViewSchema]\n    C -->|flowchart| G[FlowchartViewSchema]\n    D --> H[SnapshotVersioning::append]\n    E --> H\n    F --> H\n    G --> H\n    H --> I[(snapshot_versions)]\n    H --> J[Preview HTML cache]\n    I --> K[Workbench UI]",
                ],
            ],
            [
                'slug' => 'launch-readiness',
                'title' => 'Launch readiness report',
                'view_type' => 'report',
                'payload' => [
                    'blocks' => [
                        [
                            'type' => 'markdown',
                            'body' => "# Launch readiness\n\nA running review of what's ready, what's blocked, and what's still in flight before the public launch.",
                        ],
                        [
                            'type' => 'markdown',
                            'body' => "## Engineering\n\n- Migration plan signed off by infra\n- Final load test scheduled for Friday\n- Two SEV-3 incidents still open (see incidents table)",
                        ],
                        [
                            'type' => 'markdown',
                            'body' => "## Risks\n\n- EU data residency review still pending\n- Marketing site copy needs legal pass\n- One on-call engineer out next week",
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'design-system-readme',
                'title' => 'Design system readme',
                'view_type' => 'report',
                'payload' => [
                    'blocks' => [
                        [
                            'type' => 'markdown',
                            'body' => $this->demoReportMarkdown(),
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * A long-form markdown body that showcases the new Source Serif 4 prose
     * styling — headings (Inter), body (Source Serif 4), and code (JetBrains
     * Mono) — along with lists, blockquotes, and a link.
     */
    private function demoReportMarkdown(): string
    {
        return <<<'MARKDOWN'
# The Nexus Design System

The Nexus workbench renders agent output the way a print designer would lay out
a long magazine feature: editorial headings, an unhurried serif body, and a
monospace face reserved for code. This page exists so that anyone tweaking the
type ramp or the dark theme can land on a single snapshot and see every prose
element at once.

## Why a serif body?

Most dashboards default to the same humanist sans for everything: nav, table
cells, bodies of explanatory text. That's fine until an agent posts a 600-word
investigation summary, and the reader's eye fatigues halfway down the page.
Source Serif 4 was picked for the body face because it holds up at small sizes,
pairs cleanly with Inter, and has matching italics that don't fight the roman.

> The body is for reading. The headings are for scanning. The code is for
> copying. Three jobs, three faces — keep them honest.

### What changed in M9

The M9 milestone pulled the type system in a few directions at once:

1. Adopted Source Serif 4 as the default body face.
2. Re-tuned the heading scale so h1 and h2 feel distinctly different.
3. Promoted JetBrains Mono to the canonical code face.
4. Added a warm-graphite dark theme that keeps the same hue family.

Unordered lists work too:

- Headings remain in Inter for scannability.
- Body copy switches to Source Serif 4 for long reads.
- Inline `code` and code blocks render in JetBrains Mono.
- Tables, badges, and chrome stay in the sans face for density.

## A code sample

Agents frequently include short code blocks in their reports. The fenced block
below should render in JetBrains Mono with a token-derived background that
holds up in both themes:

```php
<?php

declare(strict_types=1);

namespace App\Nexus;

final class HelloWorld
{
    public function greet(string $name): string
    {
        return "Hello, {$name}!";
    }
}
```

JavaScript snippets behave the same way:

```js
const greet = (name) => `Hello, ${name}!`;
console.log(greet('Nexus'));
```

## Further reading

For the full requirement list and the rationale behind each token, see the
[Nexus spec](https://example.test/docs/nexus-spec) — every type, radius, and
shadow choice is keyed to a REQ-ID so the design system can be audited the
same way the code is.
MARKDOWN;
    }
}
