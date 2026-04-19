<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

/*
 * These tests are meta — they verify that the `spec:check` command itself
 * enforces the contract described in AGENTS.md. They must remain green at
 * all times; if they ever fail, the tracker is broken and every other
 * REQ-ID's green status is untrustworthy.
 */

beforeEach(function () {
    $this->tmpDir = sys_get_temp_dir().'/nexus-spec-check-'.bin2hex(random_bytes(4));
    mkdir($this->tmpDir.'/docs', recursive: true);
    mkdir($this->tmpDir.'/tests/Feature', recursive: true);
});

afterEach(function () {
    if (is_dir($this->tmpDir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tmpDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $path = $item->getPathname();
            $item->isDir() ? rmdir($path) : unlink($path);
        }
        rmdir($this->tmpDir);
    }
});

function writeSpec(string $dir, string $body): void
{
    file_put_contents($dir.'/docs/nexus-spec.md', $body);
}

function writeTest(string $dir, string $filename, string $body): void
{
    file_put_contents($dir.'/tests/Feature/'.$filename, $body);
}

it('REQ-P0B-001: spec:check fails when a REQ-ID has no matching test', function () {
    writeSpec($this->tmpDir, <<<'MD'
    ## Section
    - **REQ-FAKE-001** A thing we care about.
    MD);

    $exit = Artisan::call('spec:check', [
        '--spec' => $this->tmpDir.'/docs/nexus-spec.md',
        '--tests' => $this->tmpDir.'/tests',
    ]);

    expect($exit)->toBe(1);
    expect(Artisan::output())->toContain('REQ-FAKE-001')->toContain('missing test');
});

it('REQ-P0B-002: spec:check passes when every REQ-ID has a matching test', function () {
    writeSpec($this->tmpDir, <<<'MD'
    ## Section
    - **REQ-FAKE-001** A thing.
    - **REQ-FAKE-002** Another thing.
    MD);

    writeTest($this->tmpDir, 'OneTest.php', <<<'PHP'
    <?php it('REQ-FAKE-001: covers first thing', fn () => null);
    PHP);
    writeTest($this->tmpDir, 'TwoTest.php', <<<'PHP'
    <?php it('REQ-FAKE-002: covers second thing', fn () => null);
    PHP);

    $exit = Artisan::call('spec:check', [
        '--spec' => $this->tmpDir.'/docs/nexus-spec.md',
        '--tests' => $this->tmpDir.'/tests',
    ]);

    expect($exit)->toBe(0);
    expect(Artisan::output())
        ->toContain('2/2 requirements')
        ->toContain('All requirements fulfilled');
});

it('REQ-P0B-003: spec:check --next returns the first unfulfilled REQ-ID', function () {
    writeSpec($this->tmpDir, <<<'MD'
    ## Section
    - **REQ-FAKE-001** First.
    - **REQ-FAKE-002** Second.
    - **REQ-FAKE-003** Third.
    MD);

    // Mark the first one done; --next must surface the second.
    writeTest($this->tmpDir, 'OneTest.php', <<<'PHP'
    <?php it('REQ-FAKE-001: done', fn () => null);
    PHP);

    $exit = Artisan::call('spec:check', [
        '--next' => true,
        '--spec' => $this->tmpDir.'/docs/nexus-spec.md',
        '--tests' => $this->tmpDir.'/tests',
    ]);

    expect($exit)->toBe(0);
    expect(trim(Artisan::output()))->toBe('REQ-FAKE-002');
});
