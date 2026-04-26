<?php

declare(strict_types=1);

/**
 * REQ-M9-002 — Replace ad-hoc font sizes with a modular typographic scale
 * anchored at 16 px base. Define --text-xs through --text-4xl plus matching
 * --leading-* tokens in resources/css/app.css and expose via Tailwind's
 *
 * @theme block. Update shared chrome (app-sidebar, app-header, page titles)
 * to consume the new tokens. Component-level overrides that duplicate a
 * token are removed.
 *
 * The cheapest assertion that proves the work: parse the @theme block of
 * resources/css/app.css and confirm (a) all eight type tokens and matching
 * leading tokens are present, (b) the scale is monotonically increasing
 * from xs to 4xl, (c) the base is 1rem (16 px). For the chrome wiring,
 * confirm that page titles in heading.tsx use a Tailwind text-* utility
 * (which resolves to a token) rather than an arbitrary px size.
 */
function extractCssVar(string $css, string $name): ?string
{
    if (preg_match('/'.preg_quote($name, '/').':\s*([^;]+);/', $css, $m)) {
        return trim($m[1]);
    }

    return null;
}

function remToFloat(string $value): float
{
    $value = trim($value);
    if (str_ends_with($value, 'rem')) {
        return (float) substr($value, 0, -3);
    }
    if (str_ends_with($value, 'px')) {
        return (float) substr($value, 0, -2) / 16.0;
    }

    return (float) $value;
}

it('REQ-M9-002: defines a modular type scale --text-xs through --text-4xl in @theme anchored at 1rem base', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toBeString();

    $sizes = ['xs', 'sm', 'base', 'lg', 'xl', '2xl', '3xl', '4xl'];

    foreach ($sizes as $size) {
        $value = extractCssVar($css, '--text-'.$size);
        expect($value)->not->toBeNull("--text-{$size} should be defined in app.css");
    }

    // Base must be exactly 16px / 1rem.
    $base = extractCssVar($css, '--text-base');
    expect(remToFloat($base))->toBe(1.0);
});

it('REQ-M9-002: defines matching --leading-* tokens for the type scale in @theme', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    $sizes = ['xs', 'sm', 'base', 'lg', 'xl', '2xl', '3xl', '4xl'];

    foreach ($sizes as $size) {
        $value = extractCssVar($css, '--leading-'.$size);
        expect($value)->not->toBeNull("--leading-{$size} should be defined in app.css");
    }
});

it('REQ-M9-002: the type scale is monotonically increasing from xs to 4xl', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    $sizes = ['xs', 'sm', 'base', 'lg', 'xl', '2xl', '3xl', '4xl'];
    $values = [];

    foreach ($sizes as $size) {
        $raw = extractCssVar($css, '--text-'.$size);
        $values[$size] = remToFloat($raw);
    }

    $previous = 0.0;
    foreach ($sizes as $size) {
        expect($values[$size])
            ->toBeGreaterThan(
                $previous,
                "--text-{$size} ({$values[$size]}rem) must be greater than the previous step ({$previous}rem)"
            );
        $previous = $values[$size];
    }
});

it('REQ-M9-002: the type scale tokens live inside the Tailwind @theme block', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    // Locate the @theme block.
    $themeStart = strpos($css, '@theme');
    expect($themeStart)->not->toBeFalse();

    // Find matching brace.
    $braceOpen = strpos($css, '{', $themeStart);
    $depth = 0;
    $themeEnd = $braceOpen;
    for ($i = $braceOpen; $i < strlen($css); $i++) {
        if ($css[$i] === '{') {
            $depth++;
        } elseif ($css[$i] === '}') {
            $depth--;
            if ($depth === 0) {
                $themeEnd = $i;
                break;
            }
        }
    }

    $themeBlock = substr($css, $themeStart, $themeEnd - $themeStart);

    expect($themeBlock)->toContain('--text-xs');
    expect($themeBlock)->toContain('--text-base');
    expect($themeBlock)->toContain('--text-4xl');
    expect($themeBlock)->toContain('--leading-xs');
    expect($themeBlock)->toContain('--leading-4xl');
});

it('REQ-M9-002: page titles in heading.tsx consume Tailwind text-* utilities (not raw px values)', function () {
    $heading = file_get_contents(resource_path('js/components/heading.tsx'));

    expect($heading)->toBeString();
    // Page title must use a text-* token-driven utility.
    expect($heading)->toMatch('/\btext-(xs|sm|base|lg|xl|2xl|3xl|4xl)\b/');
    // No arbitrary pixel sizes like text-[15px] sneaking back in.
    expect($heading)->not->toMatch('/text-\[\d+px\]/');
});
