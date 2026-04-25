<?php

declare(strict_types=1);

namespace App\Nexus\Schemas;

use Illuminate\Support\Str;

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
 * REQ-M7-001: cards that omit `id` are auto-assigned a UUID v4. When the
 * caller supplies the previous revision's payload, ids are best-effort
 * carried forward by matching (a) explicit `id` and (b) the
 * `(column_key, title)` tuple. The validator's return value exposes the
 * resolved ids so callers can persist them.
 *
 * REQ-M7-005: cards may carry three optional, back-compatible fields:
 *  - `link_url` — a string URL that must pass `FILTER_VALIDATE_URL`;
 *  - `status` — one of `ok`, `warn`, `error`;
 *  - `assignee` — a string of at most 64 characters.
 * None of these participate in REQ-M7-001 carry-forward matching.
 *
 * Validation throws {@see KanbanViewSchemaException} with a clear, dot-path
 * message pointing at the first offending field.
 */
final class KanbanViewSchema
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $previousPayload
     * @return array<string, mixed>
     *
     * @throws KanbanViewSchemaException
     */
    public static function validate(array $payload, ?array $previousPayload = null): array
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

            // REQ-M7-005: optional link_url must be a valid URL.
            if (isset($card['link_url'])) {
                if (! is_string($card['link_url']) || filter_var($card['link_url'], FILTER_VALIDATE_URL) === false) {
                    throw new KanbanViewSchemaException(
                        "data_payload.cards[{$index}].link_url must be a valid URL.",
                    );
                }
            }

            // REQ-M7-005: optional status must be one of ok|warn|error.
            if (isset($card['status'])) {
                if (! is_string($card['status']) || ! in_array($card['status'], ['ok', 'warn', 'error'], true)) {
                    throw new KanbanViewSchemaException(
                        "data_payload.cards[{$index}].status must be one of 'ok', 'warn', or 'error'.",
                    );
                }
            }

            // REQ-M7-005: optional assignee must be a string with ≤64 chars.
            if (isset($card['assignee'])) {
                if (! is_string($card['assignee'])) {
                    throw new KanbanViewSchemaException(
                        "data_payload.cards[{$index}].assignee must be a string when provided.",
                    );
                }

                if (mb_strlen($card['assignee']) > 64) {
                    throw new KanbanViewSchemaException(
                        "data_payload.cards[{$index}].assignee must be 64 characters or fewer.",
                    );
                }
            }
        }

        // REQ-M7-001: resolve ids on the validated cards.
        $payload['cards'] = self::resolveCardIds(
            cards: array_values($payload['cards']),
            previousPayload: $previousPayload,
        );

        return $payload;
    }

    /**
     * Build a stable id for every card.
     *
     * Resolution strategy (REQ-M7-001):
     *  1. If the card carries an explicit `id`, keep it.
     *  2. Otherwise, attempt to inherit the previous revision's id by:
     *     a. matching on explicit `id` (cannot apply when there's no current
     *        id; included for symmetry); then
     *     b. matching on the `(column_key, title)` tuple.
     *  3. Otherwise, mint a fresh UUID v4.
     *
     * Carry-forward is best-effort: if a `(column_key, title)` tuple appears
     * twice in the previous revision, the first unmatched occurrence is
     * consumed in document order. Each previous-card id can only be
     * inherited once so two new cards never collapse onto the same id.
     *
     * @param  array<int, array<string, mixed>>  $cards
     * @param  array<string, mixed>|null  $previousPayload
     * @return array<int, array<string, mixed>>
     */
    private static function resolveCardIds(array $cards, ?array $previousPayload): array
    {
        $previousById = [];
        /** @var array<string, list<string>> $previousByTuple */
        $previousByTuple = [];

        if (is_array($previousPayload) && isset($previousPayload['cards']) && is_array($previousPayload['cards'])) {
            foreach (array_values($previousPayload['cards']) as $previousCard) {
                if (! is_array($previousCard)) {
                    continue;
                }

                $previousId = $previousCard['id'] ?? null;

                if (! is_string($previousId) && ! is_int($previousId)) {
                    continue;
                }

                $previousById[(string) $previousId] = $previousId;

                $columnKey = $previousCard['column_key'] ?? null;
                $title = $previousCard['title'] ?? null;

                if (is_string($columnKey) && is_string($title)) {
                    $tupleKey = self::tupleKey($columnKey, $title);
                    $previousByTuple[$tupleKey] ??= [];
                    $previousByTuple[$tupleKey][] = (string) $previousId;
                }
            }
        }

        $consumed = [];

        foreach ($cards as $i => $card) {
            $existingId = $card['id'] ?? null;

            if (is_string($existingId) || is_int($existingId)) {
                // Honour the caller's explicit id verbatim.
                $cards[$i]['id'] = $existingId;
                $consumed[(string) $existingId] = true;

                continue;
            }

            $tupleKey = self::tupleKey($card['column_key'], $card['title']);
            $candidate = null;

            if (isset($previousByTuple[$tupleKey])) {
                foreach ($previousByTuple[$tupleKey] as $previousId) {
                    if (! isset($consumed[(string) $previousId])) {
                        $candidate = $previousById[(string) $previousId] ?? $previousId;
                        break;
                    }
                }
            }

            if ($candidate !== null) {
                $cards[$i]['id'] = $candidate;
                $consumed[(string) $candidate] = true;

                continue;
            }

            $cards[$i]['id'] = (string) Str::uuid();
        }

        return $cards;
    }

    private static function tupleKey(mixed $columnKey, mixed $title): string
    {
        return ((string) $columnKey)."\0".((string) $title);
    }
}
