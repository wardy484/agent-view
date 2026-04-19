<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

/**
 * `php artisan spec:check`
 *
 * Enforces 1:1 correspondence between REQ-IDs declared in docs/nexus-spec.md
 * and Pest tests whose `it(...)` names reference those IDs.
 *
 * Exits non-zero on any miss. CI runs this on every PR.
 */
final class SpecCheck extends Command
{
    protected $signature = 'spec:check
        {--next : Print the first unfulfilled REQ-ID and exit 0}
        {--milestone= : Limit the check to a scope (e.g. M1, P0B)}
        {--spec=docs/nexus-spec.md : Path to the spec file}
        {--tests=tests : Directory to scan for Pest tests}';

    protected $description = 'Verify every REQ-ID in the spec has a matching Pest test.';

    /**
     * Matches `REQ-<scope>-<nnn>` where scope is uppercase alphanumeric and nnn is 3 digits.
     */
    private const REQ_ID_PATTERN = '/REQ-[A-Z0-9]+-\d{3}/';

    public function handle(): int
    {
        $specPath = (string) $this->option('spec');
        $testsPath = (string) $this->option('tests');
        $milestone = $this->option('milestone');

        if (! is_file($specPath)) {
            $this->components->error("Spec file not found: {$specPath}");

            return self::FAILURE;
        }

        $declared = $this->extractIds(file_get_contents($specPath) ?: '');
        $fulfilled = $this->scanTests($testsPath);

        if ($milestone !== null && $milestone !== '') {
            $declared = array_values(array_filter(
                $declared,
                fn (string $id): bool => str_contains($id, "-{$milestone}-"),
            ));
        }

        $missing = array_values(array_diff($declared, $fulfilled));

        if ($this->option('next')) {
            if ($missing === []) {
                $this->line('');

                return self::SUCCESS;
            }
            $this->line($missing[0]);

            return self::SUCCESS;
        }

        $total = count($declared);
        $done = $total - count($missing);

        $this->components->info(sprintf(
            '%d/%d requirements have tests%s',
            $done,
            $total,
            $milestone ? " (scope: {$milestone})" : '',
        ));

        if ($missing === []) {
            $this->components->success('All requirements fulfilled.');

            return self::SUCCESS;
        }

        foreach ($missing as $id) {
            $this->components->twoColumnDetail($id, '<fg=red>missing test</>');
        }

        $this->components->error(sprintf('%d requirement(s) without tests.', count($missing)));

        return self::FAILURE;
    }

    /**
     * @return list<string>
     */
    private function extractIds(string $text): array
    {
        preg_match_all(self::REQ_ID_PATTERN, $text, $matches);

        // Only count IDs declared in a line that looks like a bullet or heading —
        // avoids counting the same ID multiple times when it's prose-referenced.
        $declared = [];
        foreach (explode("\n", $text) as $line) {
            $trimmed = ltrim($line);
            if (! preg_match('/^(#|-|\*|\d+\.)\s/', $trimmed)) {
                continue;
            }
            if (preg_match_all(self::REQ_ID_PATTERN, $trimmed, $m)) {
                foreach ($m[0] as $id) {
                    $declared[$id] = true;
                }
            }
        }

        // Preserve declaration order from the spec file — not alphabetical —
        // so `--next` surfaces requirements in the order the team intends to tackle them.
        return array_keys($declared);
    }

    /**
     * @return list<string>
     */
    private function scanTests(string $path): array
    {
        if (! is_dir($path)) {
            return [];
        }

        $found = [];
        foreach (Finder::create()->files()->in($path)->name('*.php') as $file) {
            $contents = $file->getContents();
            if (preg_match_all(self::REQ_ID_PATTERN, $contents, $matches)) {
                foreach ($matches[0] as $id) {
                    $found[$id] = true;
                }
            }
        }

        return array_keys($found);
    }
}
