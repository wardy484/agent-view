<?php

declare(strict_types=1);

use App\Nexus\Renderers\FlowchartPreviewRenderer;

it('REQ-M2-006: renders mermaid_source as an SVG preview', function (): void {
    $html = FlowchartPreviewRenderer::render([
        'mermaid_source' => "flowchart TD\nA --> B",
    ]);

    expect($html)
        ->toContain('<svg')
        ->toContain('xmlns="http://www.w3.org/2000/svg"')
        ->toContain('data-nexus-preview="flowchart"')
        ->toContain('A --&gt; B');
});

it('REQ-M2-006: escapes HTML in the mermaid_source', function (): void {
    $html = FlowchartPreviewRenderer::render([
        'mermaid_source' => '<script>alert(1)</script>',
    ]);

    expect($html)
        ->not->toContain('<script>alert(1)</script>')
        ->toContain('&lt;script&gt;');
});

it('REQ-M2-006: caps output at 64 KB even for massive sources', function (): void {
    $source = str_repeat("A --> B\n", 20_000);
    $html = FlowchartPreviewRenderer::render(['mermaid_source' => $source]);

    expect(strlen($html))->toBeLessThanOrEqual(FlowchartPreviewRenderer::BYTE_LIMIT);
    expect($html)->toContain('data-truncated="true"');
});

it('REQ-M2-006: emits a single root SVG node', function (): void {
    $html = FlowchartPreviewRenderer::render(['mermaid_source' => 'graph LR; X-->Y']);

    expect(substr_count($html, '<svg'))->toBe(1);
});
