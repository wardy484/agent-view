<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Snapshot;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use Illuminate\Console\Command;

/**
 * Seeds deterministic fixtures used by the /ui-review skill so an LLM reviewer
 * has predictable snapshots to navigate to. Always appends a fresh revision —
 * unlike DemoSnapshotSeeder, which is one-shot — so reviewers can refresh
 * after a code change without dropping the database.
 *
 * Run before each UI review:
 *   php artisan nexus:seed-ui-fixtures
 */
class SeedUiFixtures extends Command
{
    protected $signature = 'nexus:seed-ui-fixtures';

    protected $description = 'Seed deterministic fixtures for the /ui-review skill (one snapshot per view_type covering optional fields).';

    public function handle(): int
    {
        $workbench = Workbench::query()->firstOrCreate(
            ['slug' => 'ui-review'],
            ['name' => 'UI review fixtures'],
        );

        foreach ($this->fixtures() as $fixture) {
            $snapshot = Snapshot::query()->firstOrCreate(
                [
                    'workbench_id' => $workbench->id,
                    'slug' => $fixture['slug'],
                ],
                ['title' => $fixture['title']],
            );

            SnapshotVersioning::append(
                snapshot: $snapshot,
                viewType: $fixture['view_type'],
                dataPayload: $fixture['payload'],
            );

            $this->line(sprintf(
                '  → %s/%s (revision %d)',
                $workbench->slug,
                $snapshot->slug,
                $snapshot->refresh()->currentVersion?->revision ?? 0,
            ));
        }

        $this->info('UI review fixtures seeded under workbench '.$workbench->slug);

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{slug: string, title: string, view_type: string, payload: array<string, mixed>}>
     */
    private function fixtures(): array
    {
        return [
            [
                'slug' => 'kanban-all-fields',
                'title' => 'Kanban — all optional fields',
                'view_type' => 'kanban',
                'payload' => [
                    'columns' => [
                        ['key' => 'backlog', 'label' => 'Backlog'],
                        ['key' => 'in_progress', 'label' => 'In Progress'],
                        ['key' => 'in_review', 'label' => 'In Review'],
                        ['key' => 'done', 'label' => 'Done'],
                        ['key' => 'blocked', 'label' => 'Blocked'],
                    ],
                    'cards' => [
                        ['column_key' => 'backlog', 'title' => 'Plain card (back-compat)', 'body' => 'No optional fields. Should render exactly as before.'],
                        ['column_key' => 'in_progress', 'title' => 'Card with link', 'body' => 'Title should be a target=_blank anchor.', 'link_url' => 'https://example.com/issue/123'],
                        ['column_key' => 'in_progress', 'title' => 'Status: ok', 'body' => '4 px green left border stripe.', 'status' => 'ok'],
                        ['column_key' => 'in_review', 'title' => 'Status: warn', 'body' => '4 px amber left border stripe.', 'status' => 'warn'],
                        ['column_key' => 'blocked', 'title' => 'Status: error', 'body' => '4 px red left border stripe.', 'status' => 'error'],
                        ['column_key' => 'done', 'title' => 'Card with assignee', 'body' => 'Footer should show a Badge chip with the assignee text.', 'assignee' => 'agent-worker-7'],
                        ['column_key' => 'done', 'title' => 'All three optionals', 'body' => 'Link + status + assignee on one card.', 'link_url' => 'https://example.com/pr/456', 'status' => 'ok', 'assignee' => 'human'],
                    ],
                ],
            ],
        ];
    }
}
