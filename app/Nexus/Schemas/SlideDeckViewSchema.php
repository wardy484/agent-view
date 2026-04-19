<?php

declare(strict_types=1);

namespace App\Nexus\Schemas;

/**
 * REQ-M3-001: structural validator for the Slide Deck view's `data_payload`.
 *
 * The v1 Slide Deck schema requires:
 *  - `slides` — non-empty array of slide objects.
 *  - each slide has a string `title` and string `body_md`.
 *
 * Validation throws {@see SlideDeckViewSchemaException} with a clear,
 * dot-path message pointing at the first offending field.
 */
final class SlideDeckViewSchema
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws SlideDeckViewSchemaException
     */
    public static function validate(array $payload): array
    {
        $slides = $payload['slides'] ?? null;

        if (! is_array($slides) || $slides === []) {
            throw new SlideDeckViewSchemaException(
                'data_payload.slides is required and must be a non-empty array.',
            );
        }

        foreach (array_values($slides) as $index => $slide) {
            if (! is_array($slide)) {
                throw new SlideDeckViewSchemaException(
                    "data_payload.slides[{$index}] must be an object.",
                );
            }

            if (! isset($slide['title']) || ! is_string($slide['title'])) {
                throw new SlideDeckViewSchemaException(
                    "data_payload.slides[{$index}].title is required and must be a string.",
                );
            }

            if (! isset($slide['body_md']) || ! is_string($slide['body_md'])) {
                throw new SlideDeckViewSchemaException(
                    "data_payload.slides[{$index}].body_md is required and must be a string.",
                );
            }
        }

        return $payload;
    }
}
