<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('REQ-M11-002: logs in with valid credentials and redirects to the dashboard', function (): void {
    $user = User::factory()->unverified()->create([
        'email' => 'baseline-login@example.com',
        'password' => Hash::make('password'),
    ]);

    visit('/login')
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('Log in')
        ->assertPathIs('/dashboard')
        ->assertNoJavaScriptErrors();

    $this->assertAuthenticatedAs($user);
});
