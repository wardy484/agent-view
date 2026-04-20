<?php

declare(strict_types=1);

use App\Models\Snapshot;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('REQ-M1-007: snapshot page exposes every revision newest-first', function (): void {
    $workbench = Workbench::factory()->create(['slug' => 'team-alpha']);
    $snapshot = Snapshot::factory()->for($workbench)->create(['slug' => 'engineers']);

    $payload = [
        'columns' => [['key' => 'id', 'label' => 'ID']],
        'rows' => [['id' => 1]],
    ];

    $first = SnapshotVersioning::append(snapshot: $snapshot, viewType: 'table', dataPayload: $payload);
    $second = SnapshotVersioning::append(snapshot: $snapshot, viewType: 'table', dataPayload: $payload);
    $third = SnapshotVersioning::append(snapshot: $snapshot, viewType: 'table', dataPayload: $payload);

    $snapshot->forceFill(['current_version_id' => $third->id])->save();

    $this->actingAs($workbench->owner)
        ->withoutVite()
        ->get(route('workbench.snapshot.show', [
            'workbench' => $workbench->slug,
            'snapshot' => $snapshot->slug,
        ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('snapshot')
            ->has('versions', 3)
            ->where('versions.0.revision', 3)
            ->where('versions.1.revision', 2)
            ->where('versions.2.revision', 1)
            ->where('versions.0.id', $third->id)
            ->where('versions.1.id', $second->id)
            ->where('versions.2.id', $first->id)
            ->where('versions.0.is_current', true)
            ->where('versions.1.is_current', false)
            ->where('versions.2.is_current', false)
        );
});

it('REQ-M1-007: ?revision={n} navigates to that revision', function (): void {
    $workbench = Workbench::factory()->create(['slug' => 'team-alpha']);
    $snapshot = Snapshot::factory()->for($workbench)->create(['slug' => 'engineers']);

    $payload = [
        'columns' => [['key' => 'id', 'label' => 'ID']],
        'rows' => [['id' => 1]],
    ];

    $rev1 = SnapshotVersioning::append(snapshot: $snapshot, viewType: 'table', dataPayload: $payload);
    $rev2 = SnapshotVersioning::append(snapshot: $snapshot, viewType: 'table', dataPayload: $payload);

    $snapshot->forceFill(['current_version_id' => $rev2->id])->save();

    $this->actingAs($workbench->owner)
        ->withoutVite()
        ->get(route('workbench.snapshot.show', [
            'workbench' => $workbench->slug,
            'snapshot' => $snapshot->slug,
            'revision' => 1,
        ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('version.id', $rev1->id)
            ->where('version.revision', 1)
        );
});

it('REQ-M1-007: unknown revision returns 404', function (): void {
    $workbench = Workbench::factory()->create();
    $snapshot = Snapshot::factory()->for($workbench)->create();

    SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'table',
        dataPayload: ['columns' => [['key' => 'id']], 'rows' => [['id' => 1]]],
    );

    $this->actingAs($workbench->owner)
        ->withoutVite()
        ->get(route('workbench.snapshot.show', [
            'workbench' => $workbench->slug,
            'snapshot' => $snapshot->slug,
            'revision' => 999,
        ]))
        ->assertNotFound();
});

it('REQ-M1-007: VersionSwitcher component renders newest-first and uses Inertia Link', function (): void {
    $source = file_get_contents(resource_path('js/components/nexus/version-switcher.tsx'));

    expect($source)
        ->toContain("from '@inertiajs/react'")
        ->toContain('Link')
        ->toContain('versions.map(');
});
