<?php

declare(strict_types=1);

namespace App\Mcp\Content;

use InvalidArgumentException;
use Laravel\Mcp\Server\Concerns\HasMeta;
use Laravel\Mcp\Server\Contracts\Content;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource as McpResource;
use Laravel\Mcp\Server\Tool;

/**
 * Embedded text resource for tool responses.
 *
 * Emits a content part of the form:
 *
 *   { "type": "resource",
 *     "resource": { "uri": "...", "mimeType": "text/html", "text": "..." } }
 *
 * The framework's built-in Content classes don't cover the embedded-resource
 * variant a tool may return alongside text/image/audio parts (REQ-M1-004).
 */
class EmbeddedResource implements Content
{
    use HasMeta;

    public function __construct(
        protected string $uri,
        protected string $mimeType,
        protected string $text,
    ) {
        //
    }

    /**
     * @return array<string, mixed>
     */
    public function toTool(Tool $tool): array
    {
        return $this->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function toPrompt(Prompt $prompt): array
    {
        throw new InvalidArgumentException('EmbeddedResource content may not be used in prompts.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toResource(McpResource $resource): array
    {
        return $this->mergeMeta([
            'uri' => $resource->uri(),
            'mimeType' => $resource->mimeType(),
            'text' => $this->text,
        ]);
    }

    public function __toString(): string
    {
        return $this->text;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->mergeMeta([
            'type' => 'resource',
            'resource' => [
                'uri' => $this->uri,
                'mimeType' => $this->mimeType,
                'text' => $this->text,
            ],
        ]);
    }
}
