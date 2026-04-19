<?php

declare(strict_types=1);

namespace App\Nexus;

use App\Models\SnapshotVersion;
use InvalidArgumentException;

/**
 * REQ-M3-009: compute added/removed/changed rows between any two versions of
 * a table snapshot.
 *
 * Rows are keyed by the value of `columns[0].key` — the first column is the
 * stable primary key of the table. Rows whose key is missing are ignored.
 */
final class VersionDiff
{
    /**
     * @return array{
     *     added: list<array<string, mixed>>,
     *     removed: list<array<string, mixed>>,
     *     changed: list<array{key: mixed, before: array<string, mixed>, after: array<string, mixed>}>
     * }
     */
    public static function between(SnapshotVersion $a, SnapshotVersion $b): array
    {
        if ($a->view_type !== 'table' || $b->view_type !== 'table') {
            throw new InvalidArgumentException('VersionDiff::between() only supports table snapshots.');
        }

        $keyA = self::primaryKey($a->data_payload ?? []);
        $keyB = self::primaryKey($b->data_payload ?? []);

        if ($keyA !== $keyB) {
            throw new InvalidArgumentException(
                'VersionDiff::between() requires both versions to share the same primary key column.',
            );
        }

        $key = $keyA;

        $beforeRows = self::indexRows($a->data_payload['rows'] ?? [], $key);
        $afterRows = self::indexRows($b->data_payload['rows'] ?? [], $key);

        $added = [];
        $removed = [];
        $changed = [];

        foreach ($afterRows as $rowKey => $afterRow) {
            if (! array_key_exists($rowKey, $beforeRows)) {
                $added[] = $afterRow;

                continue;
            }

            if ($beforeRows[$rowKey] !== $afterRow) {
                $changed[] = [
                    'key' => $afterRow[$key] ?? $rowKey,
                    'before' => $beforeRows[$rowKey],
                    'after' => $afterRow,
                ];
            }
        }

        foreach ($beforeRows as $rowKey => $beforeRow) {
            if (! array_key_exists($rowKey, $afterRows)) {
                $removed[] = $beforeRow;
            }
        }

        return [
            'added' => array_values($added),
            'removed' => array_values($removed),
            'changed' => array_values($changed),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function primaryKey(array $payload): string
    {
        $columns = is_array($payload['columns'] ?? null) ? array_values($payload['columns']) : [];

        if ($columns === [] || ! is_array($columns[0]) || ! isset($columns[0]['key']) || ! is_string($columns[0]['key'])) {
            throw new InvalidArgumentException('VersionDiff::between() requires a table payload with at least one column.');
        }

        return $columns[0]['key'];
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    private static function indexRows(mixed $rows, string $key): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $indexed = [];
        foreach ($rows as $row) {
            if (! is_array($row) || ! array_key_exists($key, $row)) {
                continue;
            }

            $rowKey = $row[$key];

            if (! is_scalar($rowKey)) {
                continue;
            }

            $indexed[$rowKey] = $row;
        }

        return $indexed;
    }
}
