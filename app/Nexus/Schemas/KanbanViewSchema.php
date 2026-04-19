<?php

declare(strict_types=1);

namespace App\Nexus\Schemas;

/**
 * REQ-M2-001: structural validator for the Kanban view's `data_payload`.
 *
 * The v1 Kanban schema requires:
 *  - `columns` — non-empty array of objects, each with a string `key` (and an
 *    optional string `label`). Column keys must be unique.
 *  - `cards` — array (may be empty) of card objects. Each card must have a
 *    string `column_key` that references one of the declared columns and a
 *    string `title`. Optional fields: `id` (string|int), `body` (string).
 *
 * Validation throws {@see KanbanViewSchemaException} with a clear, dot-path
 * message pointing at the first offending field.
 */
final class KanbanViewSchema
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws KanbanViewSchemaException
     */
    public static function validate(array $payload): array
    {
        $columns = $payload['columns'] ?? null;

        if (! is_array($columns) || $columns === []) {
            throw new KanbanViewSchemaException(
                'data_payload.columns is required and must be a non-empty array.',
            );
        }

        $columnKeys = [];

        foreach (array_values($columns) as $index => $column) {
            if (! is_array($column) || ! isset($column['key']) || ! is_string($column['key'])) {
                throw new KanbanViewSchemaException(
                    "data_payload.columns[{$index}].key is required and must be a string.",
                );
            }

            if (isset($column['label']) && ! is_string($column['label'])) {
                throw new KanbanViewSchemaException(
                    "data_payload.columns[{$index}].label must be a string when provided.",
                );
            }

            if (isset($columnKeys[$column['key']])) {
                throw new KanbanViewSchemaException(
                    "data_payload.columns[{$index}].key '{$column['key']}' is duplicated.",
                );
            }

            $columnKeys[$column['key']] = true;
        }

        if (! array_key_exists('cards', $payload) || ! is_array($payload['cards'])) {
            throw new KanbanViewSchemaException(
                'data_payload.cards is required and must be an array.',
            );
        }

        foreach (array_values($payload['cards']) as $index => $card) {
            if (! is_array($card)) {
                throw new KanbanViewSchemaException(
                    "data_payload.cards[{$index}] must be an object.",
                );
            }

            if (! isset($card['column_key']) || ! is_string($card['column_key'])) {
                throw new KanbanViewSchemaException(
                    "data_payload.cards[{$index}].column_key is required and must be a string.",
                );
            }

            if (! isset($columnKeys[$card['column_key']])) {
                throw new KanbanViewSchemaException(
                    "data_payload.cards[{$index}].column_key '{$card['column_key']}' does not match any declared column.",
                );
            }

            if (! isset($card['title']) || ! is_string($card['title'])) {
                throw new KanbanViewSchemaException(
                    "data_payload.cards[{$index}].title is required and must be a string.",
                );
            }

            if (isset($card['body']) && ! is_string($card['body'])) {
                throw new KanbanViewSchemaException(
                    "data_payload.cards[{$index}].body must be a string when provided.",
                );
            }

            if (isset($card['id']) && ! is_string($card['id']) && ! is_int($card['id'])) {
                throw new KanbanViewSchemaException(
                    "data_payload.cards[{$index}].id must be a string or integer when provided.",
                );
            }
        }

        return $payload;
    }
}
