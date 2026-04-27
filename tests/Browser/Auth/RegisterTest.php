<?php

declare(strict_types=1);

it('REQ-M11-003: registers a new user, auto-logs in, and redirects to the dashboard', function (): void {
    $email = fake()->unique()->safeEmail();

    visit('/register')
        ->fill('name', 'Baseline Register User')
        ->fill('email', $email)
        ->fill('password', 'password')
        ->fill('password_confirmation', 'password')
        ->click('@register-user-button')
        ->assertPathIs('/dashboard')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('users', ['email' => $email]);
    $this->assertAuthenticated();
});
