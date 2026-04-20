<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Support\McpCallLogger;
use App\Models\Snapshot;
use App\Models\Workbench;
use App\Nexus\Schemas\FlowchartViewSchema;
use App\Nexus\Schemas\FlowchartViewSchemaException;
use App\Nexus\Schemas\KanbanViewSchema;
use App\Nexus\Schemas\KanbanViewSchemaException;
use App\Nexus\Schemas\SlideDeckViewSchema;
use App\Nexus\Schemas\SlideDeckViewSchemaException;
use App\Nexus\Schemas\TableViewSchema;
use App\Nexus\Schemas\TableViewSchemaException;
use App\Nexus\SnapshotVersioning;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
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
    public function handle(Request $request): ResponseFactory|Response
    {
        return McpCallLogger::record('present_structured_data', $request, fn () => $this->execute($request));
    }

    private function execute(Request $request): ResponseFactory|Response
    {
        $viewType = (string) $request->get('view_type');
        $dataPayload = (array) $request->get('data_payload');

        // REQ-M1-008: run the view-specific schema validator BEFORE any
        // workbench/snapshot rows are created so invalid payloads never
        // leak side effects. Surfaces the validator's dot-path error
        // message as an MCP tool error.
        try {
            $dataPayload = $this->validateDataPayload($viewType, $dataPayload);
        } catch (
            TableViewSchemaException
            |SlideDeckViewSchemaException
            |KanbanViewSchemaException
            |FlowchartViewSchemaException $exception
        ) {
            return Response::error($exception->getMessage());
        }

        $workbench = Workbench::query()->firstOrCreate(
            ['slug' => $request->get('workbench_slug')],
            ['name' => Str::headline((string) $request->get('workbench_slug'))],
        );

        $snapshot = $this->resolveSnapshot($request, $workbench);

        // SnapshotVersioning::append() updates current_version_id (REQ-M1-010)
        // and renders+caches preview_html at write-time (REQ-M1-012).
        $version = SnapshotVersioning::append(
            snapshot: $snapshot,
            viewType: $viewType,
            dataPayload: $dataPayload,
            metadata: $request->get('metadata') ? (array) $request->get('metadata') : null,
        );

        $url = route('workbench.snapshot.show', [
            'workbench' => $workbench->slug,
            'snapshot' => $snapshot->slug,
        ]);

        // REQ-M3-011: the iframe-embeddable URL handed to mcp-ui clients
        // carries ?mode=preview so the snapshot renders bare (no app shell)
        // when loaded inside an iframe.
        $iframeUrl = $url.(str_contains($url, '?') ? '&' : '?').'mode=preview';

        // REQ-M3-008: mcp-ui-aware clients consume a `ui://` resource whose
        // text body is the iframe-embeddable workbench URL. We emit the URL
        // as text/uri-list so the client can just load it in an iframe.
        $uiUri = 'ui://workbench/'.$workbench->slug.'/'.$snapshot->slug;

        return Response::make([
            // Text content part: the workbench URL (REQ-M1-004).
            Response::text($url),

            // text/html resource content part (REQ-M1-004).
            Response::embeddedResource($url, 'text/html', (string) $version->preview_html),

            // REQ-M3-008: ui:// resource for mcp-ui-aware clients. The body is
            // the iframe URL; the uri scheme signals "render this in an iframe".
            Response::embeddedResource($uiUri, 'text/uri-list', $iframeUrl),
        ])->withStructuredContent([
            'workbench_slug' => $workbench->slug,
            'snapshot_id' => $snapshot->getKey(),
            'snapshot_slug' => $snapshot->slug,
            'revision' => $version->revision,
            'view_type' => $version->view_type,
            'url' => $url,
        ]);
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

    /**
     * Dispatch to the matching ViewSchema validator. View types without a
     * registered schema are passed through untouched.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws TableViewSchemaException
     * @throws SlideDeckViewSchemaException
     * @throws KanbanViewSchemaException
     * @throws FlowchartViewSchemaException
     */
    private function validateDataPayload(string $viewType, array $payload): array
    {
        return match ($viewType) {
            'table' => TableViewSchema::validate($payload),
            'slide_deck' => SlideDeckViewSchema::validate($payload),
            'kanban' => KanbanViewSchema::validate($payload),
            'flowchart' => FlowchartViewSchema::validate($payload),
            default => $payload,
        };
    }

    private function resolveSnapshot(Request $request, Workbench $workbench): Snapshot
    {
        $snapshotId = $request->get('snapshot_id');
        $title = $request->get('title');

        if ($snapshotId !== null && $snapshotId !== '') {
            $snapshot = Snapshot::query()
                ->where('workbench_id', $workbench->id)
                ->where(function ($query) use ($snapshotId): void {
                    $query->where('slug', $snapshotId);

                    if (ctype_digit((string) $snapshotId)) {
                        $query->orWhere('id', (int) $snapshotId);
                    }
                })
                ->first();

            if ($snapshot !== null) {
                if ($title !== null && $title !== '') {
                    $snapshot->forceFill(['title' => (string) $title])->save();
                }

                return $snapshot;
            }
        }

        return Snapshot::query()->create([
            'workbench_id' => $workbench->id,
            'slug' => $snapshotId !== null && $snapshotId !== ''
                ? (string) $snapshotId
                : (string) Str::ulid(),
            'title' => $title !== null ? (string) $title : null,
        ]);
    }
}
