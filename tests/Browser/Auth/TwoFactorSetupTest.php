<?php

declare(strict_types=1);

use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

it('REQ-M11-004: enables two-factor auth via the security settings setup flow', function (): void {
    $user = User::factory()->create([
        'email' => 'baseline-2fa-setup@example.com',
        'password' => bcrypt('password'),
        'two_factor_secret' => null,
        'two_factor_recovery_codes' => null,
        'two_factor_confirmed_at' => null,
    ]);

    $this->actingAs($user);

    // Bypass Fortify's password.confirm middleware so the test focuses on the
    // 2FA enrolment surface itself rather than the password confirmation UI.
    session(['auth.password_confirmed_at' => time()]);

    // Open the 2FA setup modal by clicking the "Enable 2FA" button.
    $page = visit('/settings/security')
        ->click('Enable 2FA')
        ->assertSee('Enable two-factor authentication');

    // After enabling, Fortify provisions a secret. Pull it back out so we can
    // mint a valid TOTP for the confirmation step.
    $freshUser = $user->fresh();
    expect($freshUser->two_factor_secret)->not->toBeNull();

    $secret = decrypt($freshUser->two_factor_secret);

    // Advance to the verification step and submit a valid TOTP.
    $totp = (new Google2FA)->getCurrentOtp($secret);

    $page->click('Continue')
        ->assertSee('Verify authentication code')
        ->fill('code', $totp)
        ->click('Confirm');

    // Confirmation persists two_factor_confirmed_at and the page rerenders
    // with the recovery codes surface available.
    $this->assertNotNull($user->fresh()->two_factor_confirmed_at);

    $page->assertSee('2FA recovery codes')
        ->assertSee('View recovery codes')
        ->assertNoJavaScriptErrors();
});
