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

    expect($validated)->toBe($payload);
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
