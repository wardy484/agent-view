<?php

declare(strict_types=1);

use App\Models\User;

it('REQ-M11-006: logs out via the global navigation control and terminates the session', function (): void {
    $user = User::factory()->create([
        'email' => 'baseline-logout@example.com',
    ]);

    $page = visit('/login')
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('@login-button')
        ->assertPathIs('/dashboard');

    // Open the user dropdown via the sidebar trigger, then click the logout
    // control. The logout link is rendered as an Inertia <Link as="button">
    // that POSTs to /logout (NOT a direct request), satisfying the spec
    // requirement that logout occurs through the navigation UI.
    $page->click('[data-test="sidebar-menu-button"]')
        ->click('[data-test="logout-button"]')
        ->assertNoJavaScriptErrors();

    // Session is gone: guard reports unauthenticated, and protected pages
    // bounce back to the login screen. We assert via a fresh visit so the
    // test stays correct regardless of whether Fortify lands on `/` or
    // `/login` after logout (Fortify's default LogoutResponse redirects
    // to `/`).
    $this->assertGuest();

    visit('/dashboard')
        ->assertPathIs('/login')
        ->assertNoJavaScriptErrors();
});
