<?php

declare(strict_types=1);

use App\Nexus\Schemas\TableViewSchema;
use App\Nexus\Schemas\TableViewSchemaException;

it('REQ-M1-008: validate() accepts a well-formed payload', function (): void {
    $payload = [
        'columns' => [
            ['key' => 'id', 'label' => 'ID'],
            ['key' => 'name', 'label' => 'Name'],
        ],
        'rows' => [
            ['id' => 1, 'name' => 'Ada'],
        ],
    ];

    $validated = TableViewSchema::validate($payload);

    expect($validated)->toBe($payload);
});

it('REQ-M1-008: validate() rejects a payload missing columns', function (): void {
    expect(fn () => TableViewSchema::validate(['rows' => [['id' => 1]]]))
        ->toThrow(
            TableViewSchemaException::class,
            'data_payload.columns is required and must be a non-empty array.',
        );
});

it('REQ-M1-008: validate() rejects a payload missing rows', function (): void {
    expect(fn () => TableViewSchema::validate(['columns' => [['key' => 'id']]]))
        ->toThrow(
            TableViewSchemaException::class,
            'data_payload.rows is required and must be an array.',
        );
});

it('REQ-M1-008: validate() rejects an empty columns array', function (): void {
    expect(fn () => TableViewSchema::validate(['columns' => [], 'rows' => []]))
        ->toThrow(
            TableViewSchemaException::class,
            'data_payload.columns is required and must be a non-empty array.',
        );
});

it('REQ-M1-008: validate() rejects column entries without a string key', function (): void {
    expect(fn () => TableViewSchema::validate([
        'columns' => [['label' => 'No Key Here']],
        'rows' => [],
    ]))->toThrow(
        TableViewSchemaException::class,
        'data_payload.columns[0].key is required and must be a string.',
    );
});

it('REQ-M1-008: validate() rejects non-array rows', function (): void {
    expect(fn () => TableViewSchema::validate([
        'columns' => [['key' => 'id']],
        'rows' => 'not-an-array',
    ]))->toThrow(
        TableViewSchemaException::class,
        'data_payload.rows is required and must be an array.',
    );
});
