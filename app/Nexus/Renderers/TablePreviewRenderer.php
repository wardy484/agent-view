<?php

declare(strict_types=1);

namespace App\Nexus\Renderers;

/**
 * REQ-M1-011: server-rendered HTML preview of a Table view payload.
 *
 *  - Truncates to 50 rows; appends a "Showing 50 of N" footer when truncated.
 *  - Hard-caps output at 64 KB (further row drops + an explicit notice if
 *    even 50 rows blow the budget).
 *  - All text is HTML-escaped — payloads come from arbitrary MCP clients.
 */
final class TablePreviewRenderer
{
    public const ROW_LIMIT = 50;

    public const BYTE_LIMIT = 64 * 1024;

    /**
     * @param  array<string, mixed>  $payload  expected shape: { columns: [{key, label?}], rows: [{...}] }
     */
    public static function render(array $payload): string
    {
        $columns = is_array($payload['columns'] ?? null) ? array_values($payload['columns']) : [];
        $rows = is_array($payload['rows'] ?? null) ? array_values($payload['rows']) : [];
        $totalRows = count($rows);

        $truncated = $totalRows > self::ROW_LIMIT;
        $visible = $truncated ? array_slice($rows, 0, self::ROW_LIMIT) : $rows;

        $html = self::build($columns, $visible, $totalRows, $truncated, shownCount: count($visible));

        // Belt-and-braces: if the payload is so wide that 50 rows still exceed
        // the 64 KB budget, drop rows from the tail until we're under.
        while (strlen($html) > self::BYTE_LIMIT && $visible !== []) {
            array_pop($visible);
            $html = self::build($columns, $visible, $totalRows, truncated: true, shownCount: count($visible));
        }

        return $html;
    }

    /**
     * @param  list<array<string, mixed>>  $columns
     * @param  list<array<string, mixed>>  $visibleRows
     */
    private static function build(array $columns, array $visibleRows, int $totalRows, bool $truncated, int $shownCount): string
    {
        $head = '<thead><tr>';
        foreach ($columns as $column) {
            $label = (string) ($column['label'] ?? $column['key'] ?? '');
            $head .= '<th>'.e($label).'</th>';
        }
        $head .= '</tr></thead>';

        $body = '<tbody>';
        foreach ($visibleRows as $row) {
            $body .= '<tr>';
            foreach ($columns as $column) {
                $key = (string) ($column['key'] ?? '');
                $body .= '<td>'.e(self::stringify($row[$key] ?? null)).'</td>';
            }
            $body .= '</tr>';
        }
        $body .= '</tbody>';

        $footer = $truncated
            ? '<tfoot><tr><td data-truncated="true" colspan="'.count($columns).'">Showing '.$shownCount.' of '.$totalRows.'</td></tr></tfoot>'
            : '';

        return '<table data-nexus-preview="table" data-row-count="'.$totalRows.'">'.$head.$body.$footer.'</table>';
    }

    private static function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_string($value) || is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? '' : $encoded;
    }
}
