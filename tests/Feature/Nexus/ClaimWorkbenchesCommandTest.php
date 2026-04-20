<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Workbench;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('claims every null-owner workbench for the given user', function (): void {
    $user = User::factory()->create(['email' => 'ops@example.test']);
    $nullA = Workbench::factory()->create(['owner_user_id' => null]);
    $nullB = Workbench::factory()->create(['owner_user_id' => null]);
    $alreadyOwned = Workbench::factory()->create(); // has an owner

    $originalOwner = $alreadyOwned->owner_user_id;

    $this->artisan('nexus:claim-workbenches', ['email' => 'ops@example.test'])
        ->expectsOutputToContain('Claimed 2 workbench(es)')
        ->assertExitCode(0);

    expect($nullA->fresh()->owner_user_id)->toBe($user->id);
    expect($nullB->fresh()->owner_user_id)->toBe($user->id);
    // Workbenches that already had an owner are left untouched.
    expect($alreadyOwned->fresh()->owner_user_id)->toBe($originalOwner);
});

it('is idempotent — a second run claims nothing', function (): void {
    $user = User::factory()->create(['email' => 'ops@example.test']);
    Workbench::factory()->create(['owner_user_id' => null]);

    $this->artisan('nexus:claim-workbenches', ['email' => 'ops@example.test'])
        ->assertExitCode(0);

    $this->artisan('nexus:claim-workbenches', ['email' => 'ops@example.test'])
        ->expectsOutputToContain('No null-owner workbenches found')
        ->assertExitCode(0);
});

it('--dry-run reports without mutating', function (): void {
    User::factory()->create(['email' => 'ops@example.test']);
    $wb = Workbench::factory()->create(['owner_user_id' => null]);

    $this->artisan('nexus:claim-workbenches', ['email' => 'ops@example.test', '--dry-run' => true])
        ->expectsOutputToContain('Dry run')
        ->assertExitCode(0);

    expect($wb->fresh()->owner_user_id)->toBeNull();
});

it('fails when the email does not match a user', function (): void {
    Workbench::factory()->create(['owner_user_id' => null]);

    $this->artisan('nexus:claim-workbenches', ['email' => 'nobody@example.test'])
        ->expectsOutputToContain('No user found')
        ->assertExitCode(1);
});

it('matches email case-insensitively', function (): void {
    $user = User::factory()->create(['email' => 'Ops@Example.Test']);
    $wb = Workbench::factory()->create(['owner_user_id' => null]);

    $this->artisan('nexus:claim-workbenches', ['email' => 'ops@example.test'])
        ->assertExitCode(0);

    expect($wb->fresh()->owner_user_id)->toBe($user->id);
});
