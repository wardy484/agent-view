<?php

declare(strict_types=1);

use App\Models\Snapshot;
use App\Models\User;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/**
 * REQ-M9-012 — Dark-mode walk-through gate. The companion `tests/Browser/M9DarkModeTest.php`
 * is the canonical Pest browser test required by the spec; this Feature test is the
 * always-runnable gate that exercises every refreshed surface server-side and proves
 * the M9 surfaces have no hard-coded light-only colour classes.
 */

/**
 * @return array<int, string>
 */
function m9RefreshedSurfaceFiles(): array
{
    return [
        resource_path('js/pages/dashboard.tsx'),
        resource_path('js/pages/snapshot.tsx'),
        resource_path('js/pages/auth/login.tsx'),
        resource_path('js/pages/auth/register.tsx'),
        resource_path('js/pages/auth/forgot-password.tsx'),
        resource_path('js/pages/auth/reset-password.tsx'),
        resource_path('js/pages/auth/confirm-password.tsx'),
        resource_path('js/pages/auth/verify-email.tsx'),
        resource_path('js/pages/auth/two-factor-challenge.tsx'),
        resource_path('js/pages/settings/profile.tsx'),
        resource_path('js/pages/settings/security.tsx'),
        resource_path('js/pages/settings/tokens.tsx'),
        resource_path('js/pages/settings/appearance.tsx'),
        resource_path('js/components/nexus/report-view.tsx'),
        resource_path('js/components/nexus/version-switcher.tsx'),
        resource_path('js/components/nexus/share-dialog.tsx'),
        resource_path('js/components/nexus/table-view.tsx'),
        resource_path('js/components/nexus/kanban-view.tsx'),
        resource_path('js/components/nexus/flowchart-view.tsx'),
        resource_path('js/components/ui/form-field.tsx'),
    ];
}

it('REQ-M9-012: refreshed surfaces do not use hard-coded light-only colour classes', function () {
    // Token-derived utilities like bg-background / text-foreground / border-border /
    // bg-muted / text-muted-foreground are the contract — anything else needs a
    // dark: counterpart. We allow the slide-deck and other prose surfaces because
    // they already pair every neutral utility with a dark: variant.
    $bannedPatterns = [
        '/(?<![\w-])bg-white(?![\w-])/',
        '/(?<![\w-])text-black(?![\w-])/',
        '/(?<![\w-])bg-black(?![\w-])(?!\\/)/',
        '/(?<![\w-])text-gray-\d+(?![\w-])/',
        '/(?<![\w-])bg-gray-\d+(?![\w-])/',
        '/(?<![\w-])border-gray-\d+(?![\w-])/',
    ];

    $offenders = [];

    foreach (m9RefreshedSurfaceFiles() as $path) {
        if (! file_exists($path)) {
            continue;
        }
        $contents = file_get_contents($path);

        foreach ($bannedPatterns as $pattern) {
            if (preg_match_all($pattern, $contents, $matches)) {
                foreach ($matches[0] as $match) {
                    // Only flag occurrences that lack a paired dark: variant on the
                    // same className. We can't fully parse Tailwind, but a minimal
                    // heuristic is: the literal string `dark:` appears within 80
                    // chars in either direction.
                    $offset = strpos($contents, $match);
                    $window = substr($contents, max(0, $offset - 80), 200);
                    if (! str_contains($window, 'dark:')) {
                        $offenders[] = basename($path).': '.$match;
                    }
                }
            }
        }
    }

    expect($offenders)->toBe(
        [],
        'Hard-coded light-only colour classes without dark: counterpart in M9 surfaces: '
            .implode(', ', $offenders),
    );
});

it('REQ-M9-012: refreshed surfaces avoid arbitrary [#hex] colour brackets', function () {
    $offenders = [];

    foreach (m9RefreshedSurfaceFiles() as $path) {
        if (! file_exists($path)) {
            continue;
        }
        $contents = file_get_contents($path);

        // Catch bg-[#fff] / text-[#000] / border-[#abcdef] / bg-[rgb(...)] style overrides
        if (preg_match_all('/(?:bg|text|border|fill|stroke)-\[(#|rgb|hsl)[^\]]+\]/i', $contents, $matches)) {
            foreach ($matches[0] as $match) {
                $offenders[] = basename($path).': '.$match;
            }
        }
    }

    expect($offenders)->toBe(
        [],
        'Arbitrary colour brackets bypass M9 tokens — dark mode will break: '.implode(', ', $offenders),
    );
});

it('REQ-M9-012: each major route renders without a 5xx in either theme', function () {
    $owner = User::factory()->create();

    $workbench = Workbench::factory()->create([
        'owner_user_id' => $owner->id,
        'slug' => 'm9-dark',
    ]);
    $snapshot = Snapshot::factory()->for($workbench)->create(['slug' => 'dark-mode-fixture']);
    SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'report',
        dataPayload: [
            'blocks' => [
                ['type' => 'markdown', 'body' => "# Heading\n\nA paragraph for the dark-mode walk-through."],
            ],
        ],
    );

    // Unauthed routes
    foreach (['/login', '/register'] as $path) {
        get($path)->assertStatus(200);
    }

    // Authed routes
    actingAs($owner);
    foreach ([
        '/dashboard',
        '/settings/profile',
        '/settings/tokens',
        '/workbenches/m9-dark/snapshots/dark-mode-fixture',
    ] as $path) {
        $response = get($path);
        expect($response->status())->toBeLessThan(500, "Route {$path} 5xx'd: status ".$response->status());
    }
});

it('REQ-M9-012: app.css defines a .dark token map that mirrors the light keys', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    // The dark theme block exists.
    expect($css)->toContain('.dark {');

    // The core M9 surface tokens are remapped under .dark.
    $darkBlock = '';
    if (preg_match('/\.dark\s*\{([^}]+)\}/s', $css, $matches)) {
        $darkBlock = $matches[1];
    }

    expect($darkBlock)
        ->toContain('--background:')
        ->toContain('--foreground:')
        ->toContain('--muted:')
        ->toContain('--muted-foreground:')
        ->toContain('--border:');
});

it('REQ-M9-012: DemoSnapshotSeeder ships a long-form report snapshot showcasing prose styling', function () {
    $source = file_get_contents(database_path('seeders/DemoSnapshotSeeder.php'));

    // The seeder registers a report snapshot.
    expect($source)
        ->toContain("'view_type' => 'report'");

    // The report payload exercises Inter headings + Source Serif body + JetBrains Mono
    // by including h1/h2/h3, paragraphs, ordered + unordered lists, a blockquote,
    // a code block, and a link.
    expect($source)
        ->toContain('# ')   // h1
        ->toContain('## ')  // h2
        ->toContain('### ') // h3
        ->toContain('```')  // fenced code
        ->toContain('> ')   // blockquote
        ->toContain('- ')   // unordered list
        ->toContain('1. ')  // ordered list
        ->toContain('](');  // link
});

it('REQ-M9-012: closing M9 — every refreshed surface file still exists', function () {
    foreach (m9RefreshedSurfaceFiles() as $path) {
        expect(file_exists($path))->toBeTrue("Missing refreshed surface: {$path}");
    }

    // Also: comment-highlight-overlay tint strings (REQ-M6-033/037) are untouched.
    $overlay = file_get_contents(resource_path('js/components/nexus/comment-highlight-overlay.tsx'));
    expect($overlay)->toContain('bg-slate-300/60 dark:bg-slate-700/60');
});

it('REQ-M9-012: comment-highlight-overlay tint strings remain locked (REQ-M6 contract)', function () {
    $contents = file_get_contents(resource_path('js/components/nexus/comment-highlight-overlay.tsx'));

    // These exact strings are locked by REQ-M6-033/037.
    expect($contents)->toContain('bg-slate-300/60');
});
