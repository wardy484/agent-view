<?php

declare(strict_types=1);

/**
 * REQ-M9-005 — Sweep every component under resources/js/components/ui/* to ensure
 * it consumes the new tokens (font, radius, spacing, shadow) rather than hard-coded
 * values; remove orphaned class strings. Install Storybook (pnpm storybook, config
 * in .storybook/); add one story per shadcn primitive covering variants and states
 * so future visual regressions are catchable. Storybook is dev-only and is not
 * deployed.
 */

use Symfony\Component\Finder\Finder;

it('REQ-M9-005: ships a .storybook/main.ts config file', function () {
    $configPath = base_path('.storybook/main.ts');

    expect(file_exists($configPath))->toBeTrue('.storybook/main.ts must exist');

    $contents = file_get_contents($configPath);

    expect($contents)->toContain('@storybook/react-vite');
    expect($contents)->toContain('stories:');
});

it('REQ-M9-005: ships a .storybook/preview file that imports the app stylesheet', function () {
    $previewPath = base_path('.storybook/preview.ts');

    expect(file_exists($previewPath))->toBeTrue('.storybook/preview.ts must exist');

    $contents = file_get_contents($previewPath);

    // Tailwind v4 tokens must be available inside Storybook so stories render
    // with the design system.
    expect($contents)->toContain('app.css');
});

it('REQ-M9-005: package.json exposes a storybook script', function () {
    $package = json_decode(file_get_contents(base_path('package.json')), true);

    expect($package)->toBeArray();
    expect($package['scripts'] ?? [])
        ->toHaveKey('storybook')
        ->toHaveKey('build-storybook');
});

it('REQ-M9-005: .gitignore excludes the storybook-static build output', function () {
    $gitignore = file_get_contents(base_path('.gitignore'));

    expect($gitignore)->toContain('storybook-static');
});

it('REQ-M9-005: at least 20 shadcn primitives have a colocated *.stories.tsx file', function () {
    $finder = (new Finder)
        ->files()
        ->in(resource_path('js/components/ui'))
        ->name('*.stories.tsx');

    $count = $finder->count();

    expect($count)->toBeGreaterThanOrEqual(20, "Expected ≥20 story files under resources/js/components/ui — found {$count}");
});

it('REQ-M9-005: no UI primitive contains arbitrary rounded-[…] strings (sweep complete)', function () {
    $finder = (new Finder)
        ->files()
        ->in(resource_path('js/components/ui'))
        ->name('*.tsx')
        ->notName('*.stories.tsx');

    $offenders = [];

    foreach ($finder as $file) {
        $contents = $file->getContents();

        if (preg_match_all('/rounded-\[[^\]]+\]/', $contents, $matches)) {
            foreach ($matches[0] as $match) {
                $offenders[] = $file->getRelativePathname().': '.$match;
            }
        }
    }

    expect($offenders)->toBe([], 'Found arbitrary radius strings in UI primitives — sweep is incomplete: '.implode(', ', $offenders));
});

it('REQ-M9-005: no UI primitive contains arbitrary shadow-[…] strings (sweep complete)', function () {
    $finder = (new Finder)
        ->files()
        ->in(resource_path('js/components/ui'))
        ->name('*.tsx')
        ->notName('*.stories.tsx');

    $offenders = [];

    foreach ($finder as $file) {
        $contents = $file->getContents();

        if (preg_match_all('/shadow-\[[^\]]+\]/', $contents, $matches)) {
            foreach ($matches[0] as $match) {
                $offenders[] = $file->getRelativePathname().': '.$match;
            }
        }
    }

    expect($offenders)->toBe([], 'Found arbitrary shadow strings in UI primitives — sweep is incomplete: '.implode(', ', $offenders));
});
