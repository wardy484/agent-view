<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;

it('REQ-M11-008: mints a personal access token, displays the plaintext once, and revokes it from the listing', function (): void {
    $user = User::query()->where('email', 'wardy484@gmail.com')->firstOrFail();
    $this->actingAs($user);

    $tokenName = 'baseline-test-token';

    $page = visit('/settings/tokens');

    $page->fill('name', $tokenName)
        ->fill('workbench_slug', 'ui-baseline')
        ->click('Mint token')
        ->assertSee($tokenName)
        ->assertNoJavaScriptErrors();

    expect($page->script('document.querySelectorAll(\'[data-testid="plain-text-token"]\').length'))
        ->toBe(1);

    $tokenRow = $user->tokens()->where('name', $tokenName)->first();
    expect($tokenRow)->not->toBeNull();

    $tokenId = $tokenRow->id;

    expect(DB::table('personal_access_tokens')->where('id', $tokenId)->exists())
        ->toBeTrue();

    $page->click('Revoke')
        ->assertNoJavaScriptErrors()
        ->assertDontSee($tokenName);

    expect($user->tokens()->where('name', $tokenName)->exists())->toBeFalse();
    expect(DB::table('personal_access_tokens')->where('id', $tokenId)->exists())
        ->toBeFalse();
});
