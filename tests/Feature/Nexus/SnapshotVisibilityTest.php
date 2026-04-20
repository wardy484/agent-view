<?php

declare(strict_types=1);

use App\Enums\SnapshotVisibility;
use App\Models\Snapshot;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('REQ-M4-001: snapshots default to private visibility on create', function (): void {
    $snapshot = Snapshot::factory()->for(Workbench::factory())->create();

    expect($snapshot->visibility)->toBe(SnapshotVisibility::Private)
        ->and($snapshot->fresh()->visibility)->toBe(SnapshotVisibility::Private);
});

it('REQ-M4-001: visibility accepts all three enum values', function (): void {
    $workbench = Workbench::factory()->create();

    $private = Snapshot::factory()->for($workbench)->create(['visibility' => SnapshotVisibility::Private]);
    $link = Snapshot::factory()->for($workbench)->create(['visibility' => SnapshotVisibility::Link]);
    $shared = Snapshot::factory()->for($workbench)->create(['visibility' => SnapshotVisibility::Shared]);

    expect($private->fresh()->visibility)->toBe(SnapshotVisibility::Private)
        ->and($link->fresh()->visibility)->toBe(SnapshotVisibility::Link)
        ->and($shared->fresh()->visibility)->toBe(SnapshotVisibility::Shared);
});

it('REQ-M4-001: visibility persists as an enum cast (not raw string)', function (): void {
    $snapshot = Snapshot::factory()
        ->for(Workbench::factory())
        ->create(['visibility' => SnapshotVisibility::Link]);

    $reloaded = Snapshot::query()->findOrFail($snapshot->id);

    expect($reloaded->visibility)->toBeInstanceOf(SnapshotVisibility::class)
        ->and($reloaded->visibility)->toBe(SnapshotVisibility::Link);
});

it('REQ-M4-001: SnapshotVisibility enum exposes exactly private, link, shared', function (): void {
    $values = array_map(fn (SnapshotVisibility $v) => $v->value, SnapshotVisibility::cases());

    expect($values)->toEqualCanonicalizing(['private', 'link', 'shared']);
});

it('REQ-M4-001: sharing a snapshot does not pin a revision — current_version_id still advances on append', function (): void {
    // A viewer's snapshot read always resolves via current_version_id, so making a snapshot
    // shareable must not freeze the revision. This guards against future "pin-on-share" drift.
    $snapshot = Snapshot::factory()
        ->for(Workbench::factory())
        ->create(['visibility' => SnapshotVisibility::Link]);

    $payload = ['columns' => [['key' => 'id']], 'rows' => [['id' => 1]]];
    $first = SnapshotVersioning::append(snapshot: $snapshot, viewType: 'table', dataPayload: $payload);

    expect($snapshot->fresh()->current_version_id)->toBe($first->id);

    $second = SnapshotVersioning::append(snapshot: $snapshot, viewType: 'table', dataPayload: $payload);

    expect($snapshot->fresh()->current_version_id)->toBe($second->id)
        ->and($snapshot->fresh()->visibility)->toBe(SnapshotVisibility::Link);
});
