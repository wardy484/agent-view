<?php

declare(strict_types=1);

use App\Nexus\Schemas\KanbanViewSchema;
use App\Nexus\Schemas\KanbanViewSchemaException;

it('REQ-M2-001: validate() accepts a well-formed kanban payload', function (): void {
    $payload = [
        'columns' => [
            ['key' => 'todo', 'label' => 'To Do'],
            ['key' => 'done', 'label' => 'Done'],
        ],
        'cards' => [
            ['column_key' => 'todo', 'title' => 'Buy milk'],
            ['column_key' => 'done', 'title' => 'Ship M1', 'body' => 'All 14 REQs green.'],
        ],
    ];

    $validated = KanbanViewSchema::validate($payload);

    // REQ-M7-001 auto-assigns ids — assert the rest of the payload is intact.
    expect($validated['columns'])->toBe($payload['columns']);
    expect($validated['cards'])->toHaveCount(2);
    expect($validated['cards'][0])->toMatchArray($payload['cards'][0]);
    expect($validated['cards'][1])->toMatchArray($payload['cards'][1]);
});

it('REQ-M2-001: validate() rejects a payload missing columns', function (): void {
    expect(fn () => KanbanViewSchema::validate(['cards' => []]))
        ->toThrow(
            KanbanViewSchemaException::class,
            'data_payload.columns is required and must be a non-empty array.',
        );
});

it('REQ-M2-001: validate() rejects an empty columns array', function (): void {
    expect(fn () => KanbanViewSchema::validate(['columns' => [], 'cards' => []]))
        ->toThrow(
            KanbanViewSchemaException::class,
            'data_payload.columns is required and must be a non-empty array.',
        );
});

it('REQ-M2-001: validate() rejects a payload missing cards', function (): void {
    expect(fn () => KanbanViewSchema::validate(['columns' => [['key' => 'todo']]]))
        ->toThrow(
            KanbanViewSchemaException::class,
            'data_payload.cards is required and must be an array.',
        );
});

it('REQ-M2-001: validate() rejects a column without a string key', function (): void {
    expect(fn () => KanbanViewSchema::validate([
        'columns' => [['label' => 'No Key']],
        'cards' => [],
    ]))->toThrow(
        KanbanViewSchemaException::class,
        'data_payload.columns[0].key is required and must be a string.',
    );
});

it('REQ-M2-001: validate() rejects duplicate column keys', function (): void {
    expect(fn () => KanbanViewSchema::validate([
        'columns' => [
            ['key' => 'todo'],
            ['key' => 'todo'],
        ],
        'cards' => [],
    ]))->toThrow(
        KanbanViewSchemaException::class,
        "data_payload.columns[1].key 'todo' is duplicated.",
    );
});

it('REQ-M2-001: validate() rejects cards referencing unknown columns', function (): void {
    expect(fn () => KanbanViewSchema::validate([
        'columns' => [['key' => 'todo']],
        'cards' => [['column_key' => 'ghost', 'title' => 'Orphan']],
    ]))->toThrow(
        KanbanViewSchemaException::class,
        "data_payload.cards[0].column_key 'ghost' does not match any declared column.",
    );
});

it('REQ-M2-001: validate() rejects cards without a string title', function (): void {
    expect(fn () => KanbanViewSchema::validate([
        'columns' => [['key' => 'todo']],
        'cards' => [['column_key' => 'todo']],
    ]))->toThrow(
        KanbanViewSchemaException::class,
        'data_payload.cards[0].title is required and must be a string.',
    );
});

it('REQ-M7-001: assigns a UUID v4 id to cards that omit one', function (): void {
    $payload = [
        'columns' => [['key' => 'todo']],
        'cards' => [
            ['column_key' => 'todo', 'title' => 'A'],
            ['column_key' => 'todo', 'title' => 'B'],
        ],
    ];

    $validated = KanbanViewSchema::validate($payload);

    expect($validated['cards'])->toHaveCount(2);

    $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    foreach ($validated['cards'] as $card) {
        expect($card['id'])->toBeString()->toMatch($uuidPattern);
    }

    expect($validated['cards'][0]['id'])->not->toBe($validated['cards'][1]['id']);
});

it('REQ-M7-001: empty cards array remains legal', function (): void {
    $payload = [
        'columns' => [['key' => 'todo']],
        'cards' => [],
    ];

    $validated = KanbanViewSchema::validate($payload);

    expect($validated['cards'])->toBe([]);
});

it('REQ-M7-001: carries forward ids by explicit id match across revisions', function (): void {
    $previous = [
        'columns' => [['key' => 'todo']],
        'cards' => [
            ['id' => 'card-A', 'column_key' => 'todo', 'title' => 'Old title for A'],
            ['id' => 'card-B', 'column_key' => 'todo', 'title' => 'B'],
        ],
    ];

    $next = [
        'columns' => [['key' => 'todo']],
        'cards' => [
            ['id' => 'card-A', 'column_key' => 'todo', 'title' => 'Renamed A'],
            ['column_key' => 'todo', 'title' => 'Brand new card'],
        ],
    ];

    $validated = KanbanViewSchema::validate($next, $previous);

    expect($validated['cards'][0]['id'])->toBe('card-A');
    // Brand-new card without explicit id and no (column_key, title) match in
    // the previous payload should get a fresh UUID.
    expect($validated['cards'][1]['id'])
        ->toBeString()
        ->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i')
        ->not->toBe('card-B');
});

it('REQ-M7-005: accepts link_url, status, and assignee on cards', function (): void {
    $payload = [
        'columns' => [['key' => 'todo']],
        'cards' => [
            [
                'column_key' => 'todo',
                'title' => 'Investigate flaky test',
                'link_url' => 'https://example.com/issues/42',
                'status' => 'warn',
                'assignee' => 'kim@example.com',
            ],
        ],
    ];

    $validated = KanbanViewSchema::validate($payload);

    expect($validated['cards'][0])->toMatchArray([
        'column_key' => 'todo',
        'title' => 'Investigate flaky test',
        'link_url' => 'https://example.com/issues/42',
        'status' => 'warn',
        'assignee' => 'kim@example.com',
    ]);
});

it('REQ-M7-005: cards without the optional fields still validate', function (): void {
    $payload = [
        'columns' => [['key' => 'todo']],
        'cards' => [
            ['column_key' => 'todo', 'title' => 'Plain card'],
        ],
    ];

    $validated = KanbanViewSchema::validate($payload);

    expect($validated['cards'][0])->toMatchArray([
        'column_key' => 'todo',
        'title' => 'Plain card',
    ]);
    expect($validated['cards'][0])->not->toHaveKey('link_url');
    expect($validated['cards'][0])->not->toHaveKey('status');
    expect($validated['cards'][0])->not->toHaveKey('assignee');
});

it('REQ-M7-005: rejects malformed link_url with a dot-path message', function (): void {
    expect(fn () => KanbanViewSchema::validate([
        'columns' => [['key' => 'todo']],
        'cards' => [
            ['column_key' => 'todo', 'title' => 'Bad link', 'link_url' => 'not-a-url'],
        ],
    ]))->toThrow(
        KanbanViewSchemaException::class,
        'data_payload.cards[0].link_url must be a valid URL.',
    );
});

it('REQ-M7-005: rejects status values outside ok|warn|error', function (): void {
    expect(fn () => KanbanViewSchema::validate([
        'columns' => [['key' => 'todo']],
        'cards' => [
            ['column_key' => 'todo', 'title' => 'Bad status', 'status' => 'pending'],
        ],
    ]))->toThrow(
        KanbanViewSchemaException::class,
        "data_payload.cards[0].status must be one of 'ok', 'warn', or 'error'.",
    );
});

it('REQ-M7-005: rejects assignee longer than 64 characters', function (): void {
    expect(fn () => KanbanViewSchema::validate([
        'columns' => [['key' => 'todo']],
        'cards' => [
            ['column_key' => 'todo', 'title' => 'Long assignee', 'assignee' => str_repeat('a', 65)],
        ],
    ]))->toThrow(
        KanbanViewSchemaException::class,
        'data_payload.cards[0].assignee must be 64 characters or fewer.',
    );
});

it('REQ-M7-001: carries forward ids by (column_key, title) when explicit id is omitted', function (): void {
    $previous = [
        'columns' => [['key' => 'todo'], ['key' => 'done']],
        'cards' => [
            ['id' => 'uuid-buy-milk', 'column_key' => 'todo', 'title' => 'Buy milk'],
            ['id' => 'uuid-ship-m1', 'column_key' => 'done', 'title' => 'Ship M1'],
        ],
    ];

    $next = [
        'columns' => [['key' => 'todo'], ['key' => 'done']],
        'cards' => [
            // No explicit id but same (column_key, title) tuple as previous.
            ['column_key' => 'todo', 'title' => 'Buy milk'],
            ['column_key' => 'done', 'title' => 'Ship M1'],
        ],
    ];

    $validated = KanbanViewSchema::validate($next, $previous);

    expect($validated['cards'][0]['id'])->toBe('uuid-buy-milk');
    expect($validated['cards'][1]['id'])->toBe('uuid-ship-m1');
});
