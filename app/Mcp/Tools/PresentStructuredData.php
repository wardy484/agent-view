<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('present_structured_data')]
#[Title('Present Structured Data')]
#[Description('Render structured data (table, kanban, flowchart, slide deck) in a Nexus-UI workbench and return a shareable URL.')]
class PresentStructuredData extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        // Implementation arrives in REQ-M1-004 — for REQ-M1-001 we only
        // assert the v1 input schema is accepted.
        return Response::text('present_structured_data accepted');
    }

    /**
     * Get the tool's input schema (v1).
     *
     * Required: workbench_slug, view_type, data_payload
     * Optional: snapshot_id, title, metadata
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'workbench_slug' => $schema->string()
                ->description('Slug of the workbench that should receive this snapshot.')
                ->required(),

            'view_type' => $schema->string()
                ->description('Renderer to use for the payload (table, kanban, flowchart, slide_deck).')
                ->required(),

            'data_payload' => $schema->object()
                ->description('View-specific payload validated by the matching ViewSchema.')
                ->required(),

            'snapshot_id' => $schema->string()
                ->description('Existing snapshot to append a new revision to. Omit to create a new snapshot.'),

            'title' => $schema->string()
                ->description('Human-readable title shown in the workbench header.'),

            'metadata' => $schema->object()
                ->description('Free-form metadata persisted alongside the snapshot version.'),
        ];
    }
}
