<?php

declare(strict_types=1);

use App\Models\Snapshot;
use App\Models\Workbench;
use App\Nexus\Renderers\ReportPreviewRenderer;
use App\Nexus\SnapshotVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('REQ-M5-006: render() emits the data-nexus-preview wrapper for a markdown-only report', function (): void {
    $html = ReportPreviewRenderer::render([
        'blocks' => [
            ['type' => 'markdown', 'body' => '# Hello'],
            ['type' => 'markdown', 'body' => '## World'],
        ],
    ]);

    expect($html)
        ->toContain('data-nexus-preview="report"')
        ->toContain('data-block-index="0"')
        ->toContain('data-block-type="markdown"')
        ->toContain('# Hello')
        ->toContain('## World');
});

it('REQ-M5-006: render() escapes markdown bodies to prevent HTML injection', function (): void {
    $html = ReportPreviewRenderer::render([
        'blocks' => [
            ['type' => 'markdown', 'body' => '<script>alert(1)</script>'],
        ],
    ]);

    expect($html)
        ->not->toContain('<script>alert(1)</script>')
        ->toContain('&lt;script&gt;');
});

it('REQ-M5-006: render() emits an embed card with title and view_type for an existing snapshot', function (): void {
    $workbench = Workbench::query()->create(['slug' => 'r-wb', 'name' => 'R']);
    $table = Snapshot::query()->create([
        'workbench_id' => $workbench->id,
        'slug' => 'sales',
        'title' => 'Q4 Sales',
    ]);
    SnapshotVersioning::append(
        $table,
        'table',
        ['columns' => [['key' => 'a', 'label' => 'A']], 'rows' => [['a' => '1']]],
    );

    $html = ReportPreviewRenderer::render([
        'blocks' => [
            ['type' => 'embed', 'snapshot_id' => $table->id],
        ],
    ]);

    expect($html)
        ->toContain('data-nexus-preview="report"')
        ->toContain('data-block-type="embed"')
        ->toContain('data-embedded-snapshot-id="'.$table->id.'"')
        ->toContain('data-embedded-view-type="table"')
        ->toContain('Q4 Sales');
});

it('REQ-M5-006: render() reduces gracefully when the embed target is missing', function (): void {
    $html = ReportPreviewRenderer::render([
        'blocks' => [
            ['type' => 'embed', 'snapshot_id' => 999999],
        ],
    ]);

    expect($html)
        ->toContain('data-block-type="embed"')
        ->toContain('data-restricted="true"');
});

it('REQ-M5-006: render() produces HTML ≤ 64 KB even on huge markdown payloads', function (): void {
    // 5000 markdown blocks of ~200 chars each = ~1 MB before budgeting.
    $blocks = array_map(
        fn (int $i): array => ['type' => 'markdown', 'body' => str_repeat('x', 200)],
        range(1, 5_000),
    );

    $html = ReportPreviewRenderer::render(['blocks' => $blocks]);

    expect(strlen($html))->toBeLessThanOrEqual(64 * 1024)
        ->and($html)->toContain('data-truncated="true"');
});

it('REQ-M5-006: render() budgets per-embed bytes when many embeds are present', function (): void {
    $workbench = Workbench::query()->create(['slug' => 'budget-wb', 'name' => 'B']);

    $ids = [];
    for ($i = 0; $i < 20; $i++) {
        $snap = Snapshot::query()->create([
            'workbench_id' => $workbench->id,
            'slug' => "tbl-{$i}",
            'title' => "Snap {$i}",
        ]);
        SnapshotVersioning::append(
            $snap,
            'table',
            [
                'columns' => array_map(fn (int $c): array => ['key' => "c{$c}", 'label' => "C{$c}"], range(1, 10)),
                'rows' => array_map(
                    fn (int $r): array => array_combine(
                        array_map(fn (int $c) => "c{$c}", range(1, 10)),
                        array_map(fn (int $c) => str_repeat('y', 50), range(1, 10)),
                    ),
                    range(1, 50),
                ),
            ],
        );
        $ids[] = $snap->id;
    }

    $blocks = array_map(fn (int $id): array => ['type' => 'embed', 'snapshot_id' => $id], $ids);
    $html = ReportPreviewRenderer::render(['blocks' => $blocks]);

    expect(strlen($html))->toBeLessThanOrEqual(64 * 1024);
});
