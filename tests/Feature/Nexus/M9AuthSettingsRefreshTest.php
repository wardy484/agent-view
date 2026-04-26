<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * REQ-M9-008 — Apply the dashboard refresh pattern to `pages/auth/*`
 * (login, register, password reset, verify-email) and `pages/settings/*`
 * (tokens, profile, password). Single PR. These pages share a tighter
 * form-heavy layout, so any new form-row primitive lands here under
 * `resources/js/components/ui/`.
 */
const M9_AUTH_PAGES = [
    'login.tsx',
    'register.tsx',
    'forgot-password.tsx',
    'reset-password.tsx',
    'verify-email.tsx',
    'confirm-password.tsx',
    'two-factor-challenge.tsx',
];

const M9_SETTINGS_PAGES = [
    'profile.tsx',
    'security.tsx',
    'tokens.tsx',
    'appearance.tsx',
];

function authPageSource(string $file): string
{
    return file_get_contents(resource_path('js/pages/auth/'.$file));
}

function settingsPageSource(string $file): string
{
    return file_get_contents(resource_path('js/pages/settings/'.$file));
}

it('REQ-M9-008: ships the FormField form-row primitive under components/ui', function () {
    $path = resource_path('js/components/ui/form-field.tsx');

    expect(file_exists($path))->toBeTrue(
        'FormField primitive must be created under resources/js/components/ui/.',
    );

    $src = file_get_contents($path);

    expect($src)
        ->toContain('export { FormField }')
        ->toContain("from './label'")
        ->toContain("from './input'");
});

it('REQ-M9-008: FormField primitive has a colocated Storybook story', function () {
    $path = resource_path('js/components/ui/form-field.stories.tsx');

    expect(file_exists($path))->toBeTrue(
        'FormField must have a colocated *.stories.tsx file.',
    );

    expect(file_get_contents($path))
        ->toContain("from './form-field'")
        ->toContain('Default');
});

it('REQ-M9-008: every refreshed auth page imports FormField from components/ui', function (string $file) {
    // verify-email is just a button + status message — it has no input rows
    // and therefore does not need FormField. two-factor-challenge uses
    // InputOTP/recovery code which is an exception too.
    if (in_array($file, ['verify-email.tsx', 'two-factor-challenge.tsx'], true)) {
        expect(true)->toBeTrue();

        return;
    }

    $src = authPageSource($file);

    expect($src)->toContain("from '@/components/ui/form-field'");
    expect($src)->toContain('<FormField');
})->with(M9_AUTH_PAGES);

it('REQ-M9-008: every refreshed auth page drops the legacy nx-* utility classes', function (string $file) {
    $src = authPageSource($file);

    expect($src)
        ->not->toContain('nx-card')
        ->not->toContain('nx-btn')
        ->not->toContain('nx-stage-header')
        ->not->toContain('nx-stage-title');
})->with(M9_AUTH_PAGES);

it('REQ-M9-008: every refreshed auth page uses token-based sizes, not arbitrary text-[…]/rounded-[…]/shadow-[…]', function (string $file) {
    $src = authPageSource($file);

    expect(preg_match('/text-\[\d+(\.\d+)?px\]/', $src))->toBe(
        0,
        "auth/{$file} must use text-xs/sm/base/lg tokens, not text-[NNpx] arbitrary values.",
    );

    expect(preg_match('/rounded-\[[^\]]+\]/', $src))->toBe(
        0,
        "auth/{$file} must use rounded-md/lg, not rounded-[…] arbitrary values.",
    );

    expect(preg_match('/shadow-\[[^\]]+\]/', $src))->toBe(
        0,
        "auth/{$file} must not use arbitrary shadow-[…] values.",
    );
})->with(M9_AUTH_PAGES);

it('REQ-M9-008: every refreshed settings page wraps its content in a shadcn Card', function (string $file) {
    $src = settingsPageSource($file);

    expect($src)
        ->toContain("from '@/components/ui/card'")
        ->toContain('<Card');
})->with(M9_SETTINGS_PAGES);

it('REQ-M9-008: refreshed settings form pages use FormField for input rows', function (string $file) {
    $src = settingsPageSource($file);

    expect($src)
        ->toContain("from '@/components/ui/form-field'")
        ->toContain('<FormField');
})->with(['profile.tsx', 'security.tsx', 'tokens.tsx']);

it('REQ-M9-008: every refreshed settings page drops the legacy nx-* utility classes and arbitrary tokens', function (string $file) {
    $src = settingsPageSource($file);

    expect($src)
        ->not->toContain('nx-card')
        ->not->toContain('nx-btn')
        ->not->toContain('nx-stage-header');

    expect(preg_match('/text-\[\d+(\.\d+)?px\]/', $src))->toBe(
        0,
        "settings/{$file} must use text-xs/sm/base/lg tokens, not text-[NNpx].",
    );

    expect(preg_match('/rounded-\[[^\]]+\]/', $src))->toBe(
        0,
        "settings/{$file} must use rounded-md/lg, not rounded-[…] arbitrary values.",
    );
})->with(M9_SETTINGS_PAGES);

it('REQ-M9-008: auth and settings JSX heading/button text uses Title Case (lint clean)', function (string $relativePath) {
    $src = file_get_contents(resource_path('js/pages/'.$relativePath));

    // Same pattern as the dashboard guard: catches lowercase first letters
    // inside h1/h2/h3/Button JSX literal text.
    $tagPattern = '<(h1|h2|h3|Button|CardTitle)[^>]*>\s*([a-z])';
    preg_match_all('/'.$tagPattern.'/m', $src, $matches, PREG_SET_ORDER);

    $offenders = array_map(
        fn ($m) => '<'.$m[1].'> starts with "'.$m[2].'"',
        $matches,
    );

    expect($offenders)->toBe(
        [],
        $relativePath.' contains lowercase JSX heading/button text — fix to Title Case: '
            .implode(', ', $offenders),
    );
})->with([
    'auth/login.tsx',
    'auth/register.tsx',
    'auth/forgot-password.tsx',
    'auth/reset-password.tsx',
    'auth/verify-email.tsx',
    'auth/confirm-password.tsx',
    'auth/two-factor-challenge.tsx',
    'settings/profile.tsx',
    'settings/security.tsx',
    'settings/tokens.tsx',
    'settings/appearance.tsx',
]);

it('REQ-M9-008: profile page still renders and preserves the profile data contract', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->withoutVite()
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('settings/profile')
            ->where('mustVerifyEmail', fn ($v) => is_bool($v))
            ->etc()
        );
});

it('REQ-M9-008: tokens page still renders and preserves its data contract', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->withoutVite()
        ->get(route('tokens.edit'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('settings/tokens')
            ->has('tokens')
            ->where('plainTextToken', null)
        );
});

it('REQ-M9-008: login page still renders for guests', function () {
    $this->withoutVite()
        ->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('auth/login')
            ->where('canResetPassword', fn ($v) => is_bool($v))
            ->etc()
        );
});
