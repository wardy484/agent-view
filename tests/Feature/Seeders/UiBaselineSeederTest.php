<?php

declare(strict_types=1);

use App\Models\Snapshot;
use App\Models\SnapshotVersion;
use App\Models\User;
use App\Models\Workbench;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\UiBaselineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('REQ-M11-001: seeds one workbench and one snapshot per view_type idempotently', function (): void {
    // Provision the fixture user the seeder expects (mirrors DatabaseSeeder).
    User::query()->updateOrCreate(
        ['email' => 'wardy484@gmail.com'],
        [
            'name' => 'Wardy',
            'password' => 'password',
            'email_verified_at' => now(),
        ],
    );

    $owner = User::query()->where('email', 'wardy484@gmail.com')->firstOrFail();

    // First run.
    $this->seed(UiBaselineSeeder::class);

    $workbench = Workbench::query()->where('slug', 'ui-baseline')->firstOrFail();
    expect($workbench->owner_user_id)->toBe($owner->id);

    $expectedSnapshots = [
        'ui-baseline-table' => 'table',
        'ui-baseline-kanban' => 'kanban',
        'ui-baseline-report' => 'report',
        'ui-baseline-flowchart' => 'flowchart',
        'ui-baseline-slide_deck' => 'slide_deck',
    ];

    expect(Workbench::query()->where('slug', 'ui-baseline')->count())->toBe(1);
    expect(Snapshot::query()->where('workbench_id', $workbench->id)->count())->toBe(count($expectedSnapshots));

    foreach ($expectedSnapshots as $slug => $viewType) {
        $snapshot = Snapshot::query()
            ->where('workbench_id', $workbench->id)
            ->where('slug', $slug)
            ->firstOrFail();

        expect($snapshot->current_version_id)->not->toBeNull();

        $version = SnapshotVersion::query()->whereKey($snapshot->current_version_id)->firstOrFail();
        expect($version->view_type)->toBe($viewType);
        expect($version->revision)->toBe(1);
    }

    $versionCountAfterFirstRun = SnapshotVersion::query()
        ->whereIn('snapshot_id', Snapshot::query()->where('workbench_id', $workbench->id)->pluck('id'))
        ->count();

    // Second run — must not duplicate workbench, snapshots, or revisions.
    $this->seed(UiBaselineSeeder::class);

    expect(Workbench::query()->where('slug', 'ui-baseline')->count())->toBe(1);
    expect(Snapshot::query()->where('workbench_id', $workbench->id)->count())->toBe(count($expectedSnapshots));

    $versionCountAfterSecondRun = SnapshotVersion::query()
        ->whereIn('snapshot_id', Snapshot::query()->where('workbench_id', $workbench->id)->pluck('id'))
        ->count();

    expect($versionCountAfterSecondRun)->toBe($versionCountAfterFirstRun);
});

it('REQ-M11-001: DatabaseSeeder registers UiBaselineSeeder only when APP_ENV is testing', function (): void {
    expect(app()->environment('testing'))->toBeTrue();

    // Run the full DatabaseSeeder; it should idempotently seed both demo +
    // ui-baseline content because we are in the testing environment.
    $this->seed(DatabaseSeeder::class);

    expect(Workbench::query()->where('slug', 'ui-baseline')->exists())->toBeTrue();
    expect(Workbench::query()->where('slug', 'demo')->exists())->toBeTrue();
});
