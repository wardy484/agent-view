<?php

declare(strict_types=1);

/**
 * create-issues.php
 *
 * Reads docs/nexus-spec.md and creates one GitHub issue per REQ-ID.
 * Idempotent: skips IDs that already have an open/closed issue.
 *
 * Requires: `gh` CLI authenticated.
 *
 * Usage: php scripts/create-issues.php [--dry-run] [--milestone=M1]
 */
$args = [];
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--')) {
        [$k, $v] = array_pad(explode('=', substr($arg, 2), 2), 2, true);
        $args[$k] = $v;
    }
}
$dryRun = ! empty($args['dry-run']);
$milestone = $args['milestone'] ?? null;

$specPath = __DIR__.'/../docs/nexus-spec.md';
if (! is_file($specPath)) {
    fwrite(STDERR, "Spec file not found: {$specPath}\n");
    exit(1);
}

if (! $dryRun && ! commandExists('gh')) {
    fwrite(STDERR, "gh CLI not found. Install from https://cli.github.com/ or pass --dry-run.\n");
    exit(1);
}

$spec = file_get_contents($specPath);
[$requirements, $currentSection] = extractRequirements($spec);

if ($milestone !== null) {
    $requirements = array_filter(
        $requirements,
        fn (array $r): bool => str_contains($r['id'], "-{$milestone}-"),
    );
}

$existing = $dryRun ? [] : listExistingIssueTitles();
$created = 0;
$skipped = 0;

foreach ($requirements as $req) {
    $title = "[{$req['id']}] {$req['summary']}";
    if (isset($existing[$req['id']])) {
        fwrite(STDOUT, "skip  {$req['id']} (already exists)\n");
        $skipped++;

        continue;
    }

    $body = "Spec: `docs/nexus-spec.md`\nSection: {$req['section']}\n\n> {$req['summary']}\n\n".
        "Close this issue by landing a Pest test whose `it(...)` name starts with `{$req['id']}:`.";

    if ($dryRun) {
        fwrite(STDOUT, "DRY  {$title}\n");
        $created++;

        continue;
    }

    $cmd = sprintf(
        'gh issue create --title %s --body %s --label %s 2>&1',
        escapeshellarg($title),
        escapeshellarg($body),
        escapeshellarg('spec'),
    );
    exec($cmd, $output, $exit);
    if ($exit !== 0) {
        fwrite(STDERR, "FAIL  {$req['id']}: ".implode("\n", $output)."\n");

        continue;
    }
    fwrite(STDOUT, "✓    {$req['id']}: ".end($output)."\n");
    $created++;
}

fwrite(STDOUT, "\nCreated: {$created}   Skipped: {$skipped}\n");

/**
 * @return array{0: list<array{id: string, summary: string, section: string}>, 1: string}
 */
function extractRequirements(string $text): array
{
    $requirements = [];
    $currentSection = '';
    foreach (explode("\n", $text) as $line) {
        if (preg_match('/^#{1,3}\s+(.+)$/', $line, $m)) {
            $currentSection = trim($m[1]);

            continue;
        }
        if (preg_match('/\*\*(REQ-[A-Z0-9]+-\d{3})\*\*\s+(.+?)\s*$/', $line, $m)) {
            $requirements[] = [
                'id' => $m[1],
                'summary' => trim($m[2], ". \t"),
                'section' => $currentSection,
            ];
        }
    }

    return [$requirements, $currentSection];
}

/**
 * @return array<string, true>
 */
function listExistingIssueTitles(): array
{
    exec('gh issue list --state=all --limit=1000 --json title 2>&1', $out, $exit);
    if ($exit !== 0) {
        fwrite(STDERR, 'gh issue list failed: '.implode("\n", $out)."\n");

        return [];
    }
    $json = json_decode(implode("\n", $out), true) ?: [];
    $titles = [];
    foreach ($json as $issue) {
        if (preg_match('/\[(REQ-[A-Z0-9]+-\d{3})\]/', $issue['title'] ?? '', $m)) {
            $titles[$m[1]] = true;
        }
    }

    return $titles;
}

function commandExists(string $cmd): bool
{
    return (bool) shell_exec('command -v '.escapeshellarg($cmd));
}
