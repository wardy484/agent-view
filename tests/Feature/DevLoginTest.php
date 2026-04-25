<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/**
 * The /__dev-login route is registered conditionally inside routes/web.php
 * based on app()->environment('local'). The Laravel test runner boots the
 * application in the "testing" environment, so the route file evaluates the
 * environment guard as false and the route is never declared. To exercise
 * the happy paths we re-include the route file with the environment forced
 * to local; to exercise the 404 path we leave it untouched.
 */
function loadDevLoginRoute(): void
{
    app()->detectEnvironment(fn () => 'local');
    require base_path('routes/web.php');
}

it('dev-login:logs in the review user when env is local and the user exists', function () {
    loadDevLoginRoute();

    $user = User::factory()->create([
        'email' => 'ui-reviewer@gentle-toucan.test',
    ]);

    $response = $this->get('/__dev-login');

    $response->assertRedirect('/dashboard');
    expect(auth()->id())->toBe($user->id);
});

it('dev-login:404s when the review user has not been seeded', function () {
    loadDevLoginRoute();

    $response = $this->get('/__dev-login');

    $response->assertNotFound();
});

it('dev-login:404s in non-local env because the route is never registered', function () {
    // Do NOT call loadDevLoginRoute(); the testing-bootstrapped route file
    // never registered /__dev-login because env was not "local".
    expect(Route::has('__dev-login') || collect(Route::getRoutes())->contains(
        fn ($r) => $r->uri() === '__dev-login'
    ))->toBeFalse();

    $response = $this->get('/__dev-login');

    $response->assertNotFound();
});

it('dev-login:redirects to ?redirect=/some/path when same-origin', function () {
    loadDevLoginRoute();

    User::factory()->create(['email' => 'ui-reviewer@gentle-toucan.test']);

    $response = $this->get('/__dev-login?redirect=/settings/profile');

    $response->assertRedirect('/settings/profile');
});

it('dev-login:ignores ?redirect= when it contains :// (open-redirect guard)', function () {
    loadDevLoginRoute();

    User::factory()->create(['email' => 'ui-reviewer@gentle-toucan.test']);

    $response = $this->get('/__dev-login?redirect=https://evil.example/pwn');

    $response->assertRedirect('/dashboard');
});
