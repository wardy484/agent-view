<?php

declare(strict_types=1);

/**
 * REQ-M9-006 — Add an ESLint rule (custom plugin or pattern matcher) that warns
 * on lowercase first-letter in JSX <h1>–<h3>, <Button>, and nav text. Sweep
 * shared layouts (app-sidebar, app-header, breadcrumbs) and fix every existing
 * violation. Body text, helper text, and table-cell strings remain sentence
 * case and are ignored by the rule.
 */

use Symfony\Component\Finder\Finder;

it('REQ-M9-006: ships the no-lowercase-titles rule file', function () {
    $rulePath = base_path('eslint-rules/no-lowercase-titles.js');

    expect(file_exists($rulePath))
        ->toBeTrue('eslint-rules/no-lowercase-titles.js must exist');

    $contents = file_get_contents($rulePath);

    // Sanity-check the rule's structure: targets the right tag set and walks
    // JSX children. We are not unit-testing JS here, just guarding the
    // contract that the rule is wired up.
    expect($contents)
        ->toContain("'h1'")
        ->toContain("'h2'")
        ->toContain("'h3'")
        ->toContain("'Button'")
        ->toContain('JSXElement');
});

it('REQ-M9-006: registers the no-lowercase-titles rule in eslint.config.js', function () {
    $configPath = base_path('eslint.config.js');
    $contents = file_get_contents($configPath);

    expect($contents)
        ->toContain('no-lowercase-titles')
        ->toContain('nexus-local');
});

it('REQ-M9-006: shared chrome (app-sidebar, app-header, breadcrumbs) has no lowercase JSX heading/button text', function () {
    $sharedFiles = [
        resource_path('js/components/app-sidebar.tsx'),
        resource_path('js/components/app-header.tsx'),
        resource_path('js/components/app-sidebar-header.tsx'),
        resource_path('js/components/breadcrumbs.tsx'),
        resource_path('js/components/nav-main.tsx'),
        resource_path('js/components/nav-user.tsx'),
        resource_path('js/components/heading.tsx'),
        resource_path('js/components/app-logo.tsx'),
    ];

    $offenders = [];

    // Match opening tags for headings, Button, or nav-text containers
    // that wrap a literal lowercase first letter (JSXText, no expression).
    // Pattern: <Tag ...>lowercase…
    $tagPattern = '<(h[1-3]|Button|SidebarMenuButton|SidebarGroupLabel|NavigationMenuLink|BreadcrumbPage|SheetTitle)\b[^>]*>\s*([a-z])';

    foreach ($sharedFiles as $file) {
        if (! file_exists($file)) {
            continue;
        }

        $contents = file_get_contents($file);

        if (preg_match_all('/'.$tagPattern.'/', $contents, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $i => $match) {
                $offenders[] = basename($file).': '.$matches[1][$i][0].' starts with "'.$matches[2][$i][0].'"';
            }
        }
    }

    expect($offenders)->toBe(
        [],
        'Found lowercase JSX heading/button/nav text in shared chrome — fix or use Title Case: '.implode(', ', $offenders),
    );
});

it('REQ-M9-006: shared layouts directory has no lowercase JSX heading/button text', function () {
    $finder = (new Finder)
        ->files()
        ->in(resource_path('js/layouts'))
        ->name('*.tsx');

    $offenders = [];
    $tagPattern = '<(h[1-3]|Button|SidebarMenuButton|SidebarGroupLabel|NavigationMenuLink|BreadcrumbPage|SheetTitle)\b[^>]*>\s*([a-z])';

    foreach ($finder as $file) {
        $contents = $file->getContents();

        if (preg_match_all('/'.$tagPattern.'/', $contents, $matches)) {
            foreach ($matches[0] as $i => $_match) {
                $offenders[] = $file->getRelativePathname().': '.$matches[1][$i].' starts with "'.$matches[2][$i].'"';
            }
        }
    }

    expect($offenders)->toBe(
        [],
        'Found lowercase JSX heading/button/nav text in shared layouts: '.implode(', ', $offenders),
    );
});
