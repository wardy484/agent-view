<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

it('REQ-M1-013: settings/tokens page lists existing tokens', function (): void {
    $user = User::factory()->create();
    $user->createToken('seed', ['workbench:team-alpha']);

    $this->actingAs($user)
        ->withoutVite()
        ->get(route('tokens.edit'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('settings/tokens')
            ->has('tokens', 1)
            ->where('tokens.0.name', 'seed')
            ->where('tokens.0.abilities', ['workbench:team-alpha'])
        );
});

it('REQ-M1-013: minting a token requires a workbench_slug and returns the plain text once', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post(route('tokens.store'), [
            'name' => 'CLI on laptop',
            'workbench_slug' => 'team-alpha',
        ])
        ->assertRedirect(route('tokens.edit'));

    expect(PersonalAccessToken::query()->count())->toBe(1);

    $token = PersonalAccessToken::query()->first();
    expect($token->name)->toBe('CLI on laptop')
        ->and($token->abilities)->toBe(['workbench:team-alpha'])
        ->and($token->tokenable_id)->toBe($user->id);

    // The plain-text token is flashed to the session exactly once for the
    // user to copy — never persisted, never shown again.
    $response->assertSessionHas('plainTextToken');
});

it('REQ-M1-013: minting rejects a missing or invalid workbench_slug', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('tokens.edit'))
        ->post(route('tokens.store'), ['name' => 'No slug'])
        ->assertSessionHasErrors(['workbench_slug']);

    $this->actingAs($user)
        ->from(route('tokens.edit'))
        ->post(route('tokens.store'), ['name' => 'Bad slug', 'workbench_slug' => 'NOT a slug!'])
        ->assertSessionHasErrors(['workbench_slug']);

    expect(PersonalAccessToken::query()->count())->toBe(0);
});

it('REQ-M1-013: a user can revoke their own token', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('to-be-revoked', ['workbench:team-alpha']);

    $this->actingAs($user)
        ->delete(route('tokens.destroy', ['token' => $token->accessToken->id]))
        ->assertRedirect(route('tokens.edit'));

    expect(PersonalAccessToken::query()->count())->toBe(0);
});

it('REQ-M1-013: a user cannot revoke another user’s token', function (): void {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $bobsToken = $bob->createToken('bob-token', ['workbench:team-alpha']);

    $this->actingAs($alice)
        ->delete(route('tokens.destroy', ['token' => $bobsToken->accessToken->id]))
        ->assertNotFound();

    expect(PersonalAccessToken::query()->count())->toBe(1);
});

it('REQ-M1-013: anonymous users cannot reach /settings/tokens', function (): void {
    $this->get(route('tokens.edit'))->assertRedirect(route('login'));
});
