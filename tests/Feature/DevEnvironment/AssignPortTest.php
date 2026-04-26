<?php

declare(strict_types=1);

function runAssignPort(string $args = ''): array
{
    $script = base_path('scripts/assign-port.sh');
    $cmd = escapeshellcmd($script).' '.$args.' 2>&1';
    exec($cmd, $output, $exitCode);

    return [
        'stdout' => trim(implode("\n", $output)),
        'exit' => $exitCode,
    ];
}

it('REQ-M10-003: ships scripts/assign-port.sh as an executable script', function () {
    $script = base_path('scripts/assign-port.sh');

    expect(file_exists($script))->toBeTrue();
    expect(is_executable($script))->toBeTrue();
});

it('REQ-M10-003: prints a port in [20000, 30000) for a known slug', function () {
    $result = runAssignPort('cyan-macaw');

    expect($result['exit'])->toBe(0);
    expect($result['stdout'])->toMatch('/^\d+$/');

    $port = (int) $result['stdout'];
    expect($port)->toBeGreaterThanOrEqual(20000);
    expect($port)->toBeLessThan(30000);
});

it('REQ-M10-003: is deterministic — same slug yields same port across invocations', function () {
    $first = runAssignPort('gentle-toucan');
    $second = runAssignPort('gentle-toucan');

    expect($first['stdout'])->toBe($second['stdout']);
});

it('REQ-M10-003: produces different ports for different slugs (collision-rare)', function () {
    $a = runAssignPort('cyan-macaw');
    $b = runAssignPort('gentle-toucan');
    $c = runAssignPort('linux-dev-options');

    expect($a['stdout'])->not->toBe($b['stdout']);
    expect($b['stdout'])->not->toBe($c['stdout']);
});

it('REQ-M10-003: exits non-zero with usage when called without a slug', function () {
    $result = runAssignPort('');

    expect($result['exit'])->not->toBe(0);
    expect($result['stdout'])->toContain('Usage');
});
