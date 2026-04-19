<?php

declare(strict_types=1);

use App\Nexus\Renderers\TablePreviewRenderer;

it('REQ-M1-011: renders all rows when row count is at or below the limit', function (): void {
    $payload = [
        'columns' => [
            ['key' => 'id', 'label' => 'ID'],
            ['key' => 'name', 'label' => 'Name'],
        ],
        'rows' => array_map(fn (int $i) => ['id' => $i, 'name' => "Person {$i}"], range(1, 50)),
    ];

    $html = TablePreviewRenderer::render($payload);

    expect(substr_count($html, '<tr'))->toBe(51) // 1 header + 50 body rows
        ->and($html)->not->toContain('showing 50 of');
});

it('REQ-M1-011: truncates to 50 rows and shows the "showing 50 of N" footer', function (): void {
    $payload = [
        'columns' => [['key' => 'id', 'label' => 'ID']],
        'rows' => array_map(fn (int $i) => ['id' => $i], range(1, 200)),
    ];

    $html = TablePreviewRenderer::render($payload);

    // Body rows == 50; the truncation footer carries data-truncated="true".
    expect(substr_count($html, '<tbody>'))->toBe(1)
        ->and(preg_match_all('/<tr>\s*<td>\d+<\/td>\s*<\/tr>/', $html))->toBe(50)
        ->and($html)->toContain('Showing 50 of 200')
        ->and($html)->toContain('data-truncated="true"');
});

it('REQ-M1-011: produces HTML ≤ 64 KB even on huge wide payloads', function (): void {
    $payload = [
        'columns' => array_map(fn (int $i) => ['key' => "col{$i}", 'label' => "Column {$i}"], range(1, 30)),
        'rows' => array_map(
            fn (int $i) => array_combine(
                array_map(fn (int $c) => "col{$c}", range(1, 30)),
                array_map(fn (int $c) => str_repeat('x', 200), range(1, 30)),
            ),
            range(1, 5_000),
        ),
    ];

    $html = TablePreviewRenderer::render($payload);

    expect(strlen($html))->toBeLessThanOrEqual(64 * 1024);
});

it('REQ-M1-011: escapes cell values to prevent HTML injection', function (): void {
    $payload = [
        'columns' => [['key' => 'value', 'label' => 'Value']],
        'rows' => [['value' => '<script>alert(1)</script>']],
    ];

    $html = TablePreviewRenderer::render($payload);

    expect($html)
        ->not->toContain('<script>alert(1)</script>')
        ->toContain('&lt;script&gt;');
});
