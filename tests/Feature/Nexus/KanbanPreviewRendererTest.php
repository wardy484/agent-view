<?php

declare(strict_types=1);

use App\Nexus\Renderers\KanbanPreviewRenderer;

it('REQ-M2-003: renders columns and cards as HTML', function (): void {
    $html = KanbanPreviewRenderer::render([
        'columns' => [
            ['key' => 'todo', 'label' => 'To Do'],
            ['key' => 'done', 'label' => 'Done'],
        ],
        'cards' => [
            ['column_key' => 'todo', 'title' => 'Buy milk', 'body' => 'Whole fat.'],
            ['column_key' => 'done', 'title' => 'Ship M1'],
        ],
    ]);

    expect($html)
        ->toContain('data-nexus-preview="kanban"')
        ->toContain('data-column-key="todo"')
        ->toContain('data-column-key="done"')
        ->toContain('To Do')
        ->toContain('Done')
        ->toContain('Buy milk')
        ->toContain('Ship M1');
});

it('REQ-M2-003: escapes HTML in card titles and bodies', function (): void {
    $html = KanbanPreviewRenderer::render([
        'columns' => [['key' => 'todo', 'label' => '<script>']],
        'cards' => [['column_key' => 'todo', 'title' => '<img src=x>', 'body' => '"><b>x']],
    ]);

    expect($html)
        ->not->toContain('<script>')
        ->not->toContain('<img src=x>')
        ->toContain('&lt;script&gt;')
        ->toContain('&lt;img src=x&gt;');
});

it('REQ-M2-003: stays under the 64 KB byte cap even for massive payloads', function (): void {
    $cards = [];
    for ($i = 0; $i < 5_000; $i++) {
        $cards[] = [
            'column_key' => 'todo',
            'title' => "Card {$i}",
            'body' => str_repeat('x', 200),
        ];
    }

    $html = KanbanPreviewRenderer::render([
        'columns' => [['key' => 'todo', 'label' => 'To Do']],
        'cards' => $cards,
    ]);

    expect(strlen($html))->toBeLessThanOrEqual(KanbanPreviewRenderer::BYTE_LIMIT);
    expect($html)->toContain('data-truncated="true"');
});

it('REQ-M2-003: omits the truncated footer when all cards fit', function (): void {
    $html = KanbanPreviewRenderer::render([
        'columns' => [['key' => 'todo', 'label' => 'To Do']],
        'cards' => [
            ['column_key' => 'todo', 'title' => 'Only card'],
        ],
    ]);

    expect($html)->not->toContain('data-truncated="true"');
});
