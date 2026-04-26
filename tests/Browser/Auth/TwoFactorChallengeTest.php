<?php

declare(strict_types=1);

use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

it('REQ-M11-005: shows the 2FA challenge after login and accepts a valid TOTP code', function (): void {
    $google2fa = new Google2FA;
    $secret = $google2fa->generateSecretKey();

    $user = User::factory()->create([
        'email' => 'baseline-2fa@example.com',
        'two_factor_secret' => encrypt($secret),
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
        'two_factor_confirmed_at' => now(),
    ]);

    $totp = $google2fa->getCurrentOtp($secret);

    visit('/login')
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('@login-button')
        ->assertPathIs('/two-factor-challenge')
        ->fill('code', $totp)
        ->click('Continue')
        ->assertPathIs('/dashboard')
        ->assertNoJavaScriptErrors();
});
