<?php

declare(strict_types=1);

/**
 * REQ-M9-004 — Apply Source Serif 4 to long-form markdown via a hand-rolled
 * `.prose` utility in resources/css/app.css. The @tailwindcss/typography
 * plugin is deliberately avoided. Headings inside `.prose` stay font-sans
 * (Inter) for hierarchy contrast; body and lists are serif (Source Serif 4);
 * `pre` and inline `code` are JetBrains Mono. `report-view.tsx` blocks
 * render under `.prose`. Block-level styling does not change block IDs —
 * anchor stability from M6 is preserved.
 */
it('REQ-M9-004: defines a hand-rolled .prose utility in app.css with serif body', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toBeString();
    expect($css)->toContain('.prose');
    // The plugin is forbidden by spec — make sure no future contributor wires it in.
    expect($css)->not->toMatch('/@import\s+["\']@tailwindcss\/typography/');
    expect($css)->not->toMatch('/@plugin\s+["\']@tailwindcss\/typography/');

    // Body / paragraph / lists use the serif token.
    expect($css)->toMatch('/\.prose\s*\{[^}]*font-family:\s*var\(--font-serif\)/s');
    expect($css)->toMatch('/\.prose\s+p\s*\{[^}]*font-family:\s*var\(--font-serif\)/s');
});

it('REQ-M9-004: keeps headings inside .prose on the sans (Inter) stack', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toMatch('/\.prose\s+h1[^{]*\{[^}]*font-family:\s*var\(--font-sans\)/s');
});

it('REQ-M9-004: applies the mono token to .prose pre and inline code', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toMatch('/\.prose\s+code\s*\{[^}]*font-family:\s*var\(--font-mono\)/s');
    expect($css)->toMatch('/\.prose\s+pre\s*\{[^}]*font-family:\s*var\(--font-mono\)/s');
});

it('REQ-M9-004: report-view.tsx wraps the blocks container with the prose class', function () {
    $tsx = file_get_contents(resource_path('js/components/nexus/report-view.tsx'));

    expect($tsx)->toBeString();
    // The wrapper either has `prose` as a literal token in a className string
    // or inside the cn() helper — match either form.
    expect($tsx)->toMatch('/[\'"`]prose\b/');
});

it('REQ-M9-004: report-view.tsx still emits per-block data-comment-block-id (M6 anchor contract)', function () {
    $tsx = file_get_contents(resource_path('js/components/nexus/report-view.tsx'));

    // M6 anchors depend on this attribute being rendered on each markdown
    // block. The .prose styling must not have removed or renamed it.
    expect($tsx)->toContain('data-comment-block-id={id}');
    expect($tsx)->toContain('data-block-type="markdown"');
});
