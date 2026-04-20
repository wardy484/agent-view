<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the topbar avatar button compactly with a gap and no oversized circle', function () {
    $user = User::factory()->create([
        'name' => 'Kim Ward',
    ]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertStatus(200);

    $layoutPath = resource_path('js/layouts/app/nexus-shell-layout.tsx');
    $layout = file_get_contents($layoutPath);

    // Avatar should be small (size-5 = 20px) — not the default size-8 (32px).
    expect($layout)->toContain('size-5 rounded-full');

    // Button should allow its natural width, sit at nav height, and have a gap.
    expect($layout)->toContain('!w-auto');
    expect($layout)->toContain('!h-7');
    expect($layout)->toContain('gap-2');

    // The fallback should use the design-token neutral bg, not the accent-brand "muted".
    expect($layout)->toContain('bg-[color:var(--bg-2)]');

    // The old, oversized UserInfo composite should no longer be used here.
    expect($layout)->not->toContain("from '@/components/user-info'");
});
