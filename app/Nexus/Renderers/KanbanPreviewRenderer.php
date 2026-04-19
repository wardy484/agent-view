<?php

declare(strict_types=1);

namespace App\Nexus\Renderers;

/**
 * REQ-M2-003 / REQ-M1-011: server-rendered HTML preview of a Kanban view
 * payload.
 *
 *  - Hard-caps output at 64 KB (drops cards from the tail and then marks
 *    truncation in a footer if the budget is blown).
 *  - All text is HTML-escaped — payloads come from arbitrary MCP clients.
 */
final class KanbanPreviewRenderer
{
    public const BYTE_LIMIT = 64 * 1024;

    /**
     * @param  array<string, mixed>  $payload  expected shape: { columns: [{key, label?}], cards: [{column_key, title, body?}] }
     */
    public static function render(array $payload): string
    {
        $columns = is_array($payload['columns'] ?? null) ? array_values($payload['columns']) : [];
        $cards = is_array($payload['cards'] ?? null) ? array_values($payload['cards']) : [];

        $totalCards = count($cards);
        $visibleCards = $cards;

        $html = self::build($columns, $visibleCards, $totalCards, shownCount: count($visibleCards));

        while (strlen($html) > self::BYTE_LIMIT && $visibleCards !== []) {
            array_pop($visibleCards);
            $html = self::build($columns, $visibleCards, $totalCards, shownCount: count($visibleCards));
        }

        return $html;
    }

    /**
     * @param  list<array<string, mixed>>  $columns
     * @param  list<array<string, mixed>>  $visibleCards
     */
    private static function build(array $columns, array $visibleCards, int $totalCards, int $shownCount): string
    {
        $cardsByColumn = [];
        foreach ($visibleCards as $card) {
            $key = is_string($card['column_key'] ?? null) ? $card['column_key'] : '';
            $cardsByColumn[$key][] = $card;
        }

        $columnsHtml = '';
        foreach ($columns as $column) {
            $key = is_string($column['key'] ?? null) ? $column['key'] : '';
            $label = is_string($column['label'] ?? null) ? $column['label'] : $key;
            $columnCards = $cardsByColumn[$key] ?? [];

            $cardsHtml = '';
            foreach ($columnCards as $card) {
                $title = is_string($card['title'] ?? null) ? $card['title'] : '';
                $body = is_string($card['body'] ?? null) ? $card['body'] : '';
                $cardsHtml .= '<article class="nexus-kanban-card">'
                    .'<h3>'.e($title).'</h3>'
                    .($body !== '' ? '<p>'.e($body).'</p>' : '')
                    .'</article>';
            }

            $columnsHtml .= '<section class="nexus-kanban-column" data-column-key="'.e($key).'">'
                .'<header><h2>'.e($label).'</h2><span class="nexus-kanban-count">'.count($columnCards).'</span></header>'
                .'<div class="nexus-kanban-cards">'.$cardsHtml.'</div>'
                .'</section>';
        }

        $footer = $shownCount < $totalCards
            ? '<footer data-truncated="true">Showing '.$shownCount.' of '.$totalCards.' cards</footer>'
            : '';

        return '<div data-nexus-preview="kanban" data-column-count="'.count($columns).'" data-card-count="'.$totalCards.'">'
            .$columnsHtml
            .$footer
            .'</div>';
    }
}
