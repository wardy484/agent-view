<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Snapshot;
use App\Models\User;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use Illuminate\Database\Seeder;

/**
 * REQ-M11-001: idempotently seeds one workbench owned by a known fixture user
 * (`wardy484@gmail.com`, provisioned by {@see DatabaseSeeder}) plus exactly
 * one snapshot per supported `view_type` (`table`, `kanban`, `report`,
 * `flowchart`, `slide_deck`). Each snapshot has a stable slug derived from
 * its view type (`ui-baseline-<view_type>`).
 *
 * Distinct from {@see DemoSnapshotSeeder} so demo content can drift
 * independently from the browser test fixtures consumed by `tests/Browser/`.
 *
 * Idempotent — `firstOrCreate` is used for both the workbench and snapshots,
 * and a new revision is only appended when a snapshot was just created. Safe
 * to re-run from a single Pest test that calls `$this->seed(BrowserTestSeeder::class)`.
 */
class BrowserTestSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::query()->firstOrCreate(
            ['email' => 'wardy484@gmail.com'],
            [
                'name' => 'Baseline Fixture',
                'password' => 'password',
            ],
        );

        $workbench = Workbench::query()->firstOrCreate(
            ['slug' => 'ui-baseline'],
            [
                'name' => 'UI baseline',
                'owner_user_id' => $owner->id,
            ],
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
     * Smallest valid fixture per view_type. Slug pattern is
     * `ui-baseline-<view_type>` so Browser tests can address any baseline
     * snapshot from the view type alone.
     *
     * @return array<int, array{slug: string, title: string, view_type: string, payload: array<string, mixed>}>
     */
    private function samples(): array
    {
        return [
            [
                'slug' => 'ui-baseline-table',
                'title' => 'UI baseline table',
                'view_type' => 'table',
                'payload' => [
                    'columns' => [
                        ['key' => 'id', 'label' => 'ID'],
                        ['key' => 'name', 'label' => 'Name'],
                    ],
                    'rows' => [
                        ['id' => 'R-1', 'name' => 'Alpha'],
                        ['id' => 'R-2', 'name' => 'Beta'],
                    ],
                ],
            ],
            [
                'slug' => 'ui-baseline-kanban',
                'title' => 'UI baseline kanban',
                'view_type' => 'kanban',
                'payload' => [
                    'columns' => [
                        ['key' => 'todo', 'label' => 'To do'],
                        ['key' => 'done', 'label' => 'Done'],
                    ],
                    'cards' => [
                        ['column_key' => 'todo', 'title' => 'Write the test'],
                        ['column_key' => 'done', 'title' => 'Read the spec'],
                    ],
                ],
            ],
            [
                'slug' => 'ui-baseline-report',
                'title' => 'UI baseline report',
                'view_type' => 'report',
                'payload' => [
                    'blocks' => [
                        [
                            'type' => 'markdown',
                            'body' => "# UI baseline\n\nThis is the canonical baseline report fixture.",
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'ui-baseline-flowchart',
                'title' => 'UI baseline flowchart',
                'view_type' => 'flowchart',
                'payload' => [
                    'mermaid_source' => "flowchart TD\n    A[Start] --> B[End]",
                ],
            ],
            [
                'slug' => 'ui-baseline-slide_deck',
                'title' => 'UI baseline slide deck',
                'view_type' => 'slide_deck',
                'payload' => [
                    'slides' => [
                        [
                            'title' => 'UI baseline',
                            'body_md' => '- Canonical slide deck fixture',
                        ],
                    ],
                ],
            ],
        ];
    }
}
