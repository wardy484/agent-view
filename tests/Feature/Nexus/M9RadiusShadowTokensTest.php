<?php

declare(strict_types=1);

/**
 * REQ-M9-003 — Define --radius-sm/md/lg/xl and a coherent shadow scale
 * (--shadow-xs through --shadow-lg) inside the Tailwind v4 @theme block in
 * resources/css/app.css. Match shadcn defaults so the standard utilities
 * (`rounded-sm`, `shadow-xs`, etc.) re-derive from the new tokens. Sweep
 * resources/js/components/ui/* to remove per-component magic numbers
 * (arbitrary `rounded-[Npx]` / `shadow-[...]` strings) so primitives consume
 * the tokens rather than inlining their own values.
 */

use Symfony\Component\Finder\Finder;

it('REQ-M9-003: defines the radius scale (sm/md/lg/xl) inside the Tailwind @theme block', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toBeString();

    // The canonical shadcn anchor is --radius: 0.625rem at :root.
    expect($css)->toMatch('/--radius:\s*0\.625rem/');

    // Each tier is declared inside @theme so Tailwind's rounded-* utilities
    // re-derive from the tokens.
    expect($css)->toContain('--radius-sm:');
    expect($css)->toContain('--radius-md:');
    expect($css)->toContain('--radius-lg:');
    expect($css)->toContain('--radius-xl:');
});

it('REQ-M9-003: defines a coherent shadow scale (xs/sm/md/lg) inside the Tailwind @theme block', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain('--shadow-xs:');
    expect($css)->toContain('--shadow-sm:');
    expect($css)->toContain('--shadow-md:');
    expect($css)->toContain('--shadow-lg:');
});

it('REQ-M9-003: shadcn UI primitives carry no arbitrary rounded-[…] or shadow-[…] strings', function () {
    $finder = (new Finder)
        ->files()
        ->in(resource_path('js/components/ui'))
        ->name('*.tsx');

    $offenders = [];

    foreach ($finder as $file) {
        $contents = $file->getContents();

        if (preg_match_all('/rounded-\[[^\]]+\]/', $contents, $matches)) {
            foreach ($matches[0] as $match) {
                $offenders[] = $file->getRelativePathname().': '.$match;
            }
        }

        if (preg_match_all('/shadow-\[[^\]]+\]/', $contents, $matches)) {
            foreach ($matches[0] as $match) {
                $offenders[] = $file->getRelativePathname().': '.$match;
            }
        }
    }

    expect($offenders)->toBe([], 'Found arbitrary radius/shadow strings in UI primitives — replace with token-driven Tailwind utilities (rounded-sm/md/lg/xl, shadow-xs/sm/md/lg): '.implode(', ', $offenders));
});
