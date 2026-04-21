<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Workbench;
use App\Policies\WorkbenchPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function workbenchPolicy(): WorkbenchPolicy
{
    return app(WorkbenchPolicy::class);
}

/** @return list<string> */
function organisationalAbilities(): array
{
    return ['rename', 'pin', 'unpin', 'archive', 'unarchive', 'delete', 'restore'];
}

it('REQ-M6-001: owner may perform every organisational ability', function (): void {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);

    foreach (organisationalAbilities() as $ability) {
        expect(workbenchPolicy()->{$ability}($owner, $workbench))
            ->toBeTrue("Expected owner to be allowed to {$ability}");
    }
});

it('REQ-M6-001: non-owners are denied every organisational ability', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);

    foreach (organisationalAbilities() as $ability) {
        expect(workbenchPolicy()->{$ability}($stranger, $workbench))
            ->toBeFalse("Expected non-owner to be denied {$ability}");
    }
});

it('REQ-M6-001: guests are denied every organisational ability', function (): void {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id]);

    foreach (organisationalAbilities() as $ability) {
        expect(workbenchPolicy()->{$ability}(null, $workbench))
            ->toBeFalse("Expected guest to be denied {$ability}");
    }
});

it('REQ-M6-001: workbenches with a null owner are never organisable', function (): void {
    $user = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => null]);

    foreach (organisationalAbilities() as $ability) {
        expect(workbenchPolicy()->{$ability}($user, $workbench))
            ->toBeFalse("Expected null-owner workbench to reject {$ability}");
    }
});
