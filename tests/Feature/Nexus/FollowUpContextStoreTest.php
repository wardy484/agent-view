<?php

declare(strict_types=1);

use App\Models\FollowUpContext;
use App\Models\Snapshot;
use App\Models\User;
use App\Models\Workbench;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('REQ-M3-004: authenticated POST to /follow-ups creates a follow_up_contexts row', function (): void {
    $user = User::factory()->create();
    $workbench = Workbench::factory()->create(['slug' => 'team-alpha']);
    $snapshot = Snapshot::factory()->for($workbench)->create();

    $this->actingAs($user)
        ->from(route('workbench.snapshot.show', [
            'workbench' => $workbench->slug,
            'snapshot' => $snapshot->slug,
        ]))
        ->post(route('workbench.follow-ups.store', ['workbench' => $workbench->slug]), [
            'snapshot_id' => $snapshot->id,
            'selection' => [
                ['row_key' => 42, 'column' => 'status'],
            ],
        ])
        ->assertRedirect();

    $row = FollowUpContext::query()->firstOrFail();

    expect($row->workbench_id)->toBe($workbench->id)
        ->and($row->snapshot_id)->toBe($snapshot->id)
        ->and($row->consumed_at)->toBeNull()
        ->and($row->payload['selection'][0]['row_key'] ?? null)->toBe(42);
});

it('REQ-M3-004: guests cannot POST to /follow-ups', function (): void {
    $workbench = Workbench::factory()->create(['slug' => 'guest-blocked']);

    $this->post(route('workbench.follow-ups.store', ['workbench' => $workbench->slug]), [
        'selection' => [['row_key' => 1]],
    ])->assertRedirect();

    expect(FollowUpContext::query()->count())->toBe(0);
});
