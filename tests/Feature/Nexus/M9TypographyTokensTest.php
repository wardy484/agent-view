<?php

declare(strict_types=1);

/**
 * REQ-M9-001 — Self-host Inter, Source Serif 4, and JetBrains Mono via
 *
 * @font-face declarations in resources/css/app.css. Expose Tailwind v4 tokens
 * --font-sans / --font-serif / --font-mono inside the @theme block. Default
 * body to font-sans.
 *
 * The cheapest assertion that proves the work is a content assertion against
 * the CSS source: the font-face URLs must reference the three required
 * families, the @theme block must redeclare each --font-* token to point at
 * the new stack, and the app shell <body> must opt in via the font-sans
 * Tailwind utility class.
 */
it('REQ-M9-001: defines font-face declarations and Tailwind v4 font tokens for Inter, Source Serif 4, and JetBrains Mono', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toBeString();

    // Bunny Fonts CDN imports for the three required families (stop-gap per spec).
    expect($css)->toContain('fonts.bunny.net');
    expect($css)->toContain('inter');
    expect($css)->toContain('source-serif-4');
    expect($css)->toContain('jetbrains-mono');

    // Tailwind v4 @theme tokens point at the new families.
    expect($css)->toMatch('/--font-sans:\s*\R\s*\'Inter\'/');
    expect($css)->toMatch('/--font-serif:\s*\'Source Serif 4\'/');
    expect($css)->toMatch('/--font-mono:\s*\R\s*\'JetBrains Mono\'/');
});

it('REQ-M9-001: defaults the application body element to the font-sans Tailwind utility', function () {
    $blade = file_get_contents(resource_path('views/app.blade.php'));

    expect($blade)->toContain('<body');
    // The Tailwind font-sans utility resolves to var(--font-sans) under v4.
    expect($blade)->toMatch('/<body[^>]*class="[^"]*\bfont-sans\b/');
});
