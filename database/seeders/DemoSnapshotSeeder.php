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
        ];
    }
}
