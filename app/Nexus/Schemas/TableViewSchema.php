<?php

declare(strict_types=1);

namespace App\Nexus\Schemas;

/**
 * REQ-M1-008: structural validator for the Table view's `data_payload`.
 *
 * The v1 Table schema requires:
 *  - `columns` — non-empty array of objects, each with a string `key` (and an
 *    optional string `label`).
 *  - `rows` — array (may be empty) of objects.
 *
 * Validation throws {@see TableViewSchemaException} with a clear, dot-path
 * message pointing at the first offending field.
 */
final class TableViewSchema
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws TableViewSchemaException
     */
    public static function validate(array $payload): array
    {
        $columns = $payload['columns'] ?? null;

        if (! is_array($columns) || $columns === []) {
            throw new TableViewSchemaException(
                'data_payload.columns is required and must be a non-empty array.',
            );
        }

        foreach (array_values($columns) as $index => $column) {
            if (! is_array($column) || ! isset($column['key']) || ! is_string($column['key'])) {
                throw new TableViewSchemaException(
                    "data_payload.columns[{$index}].key is required and must be a string.",
                );
            }

            if (isset($column['label']) && ! is_string($column['label'])) {
                throw new TableViewSchemaException(
                    "data_payload.columns[{$index}].label must be a string when provided.",
                );
            }
        }

        if (! array_key_exists('rows', $payload) || ! is_array($payload['rows'])) {
            throw new TableViewSchemaException(
                'data_payload.rows is required and must be an array.',
            );
        }

        foreach (array_values($payload['rows']) as $index => $row) {
            if (! is_array($row)) {
                throw new TableViewSchemaException(
                    "data_payload.rows[{$index}] must be an object.",
                );
            }
        }

        return $payload;
    }
}
