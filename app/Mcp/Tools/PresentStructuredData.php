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
use App\Nexus\Schemas\ReportViewSchema;
use App\Nexus\Schemas\ReportViewSchemaException;
use App\Nexus\Schemas\SlideDeckViewSchema;
use App\Nexus\Schemas\SlideDeckViewSchemaException;
use App\Nexus\Schemas\TableViewSchema;
use App\Nexus\Schemas\TableViewSchemaException;
use App\Nexus\SnapshotVersioning;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
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
#[Description('Render structured data (table, kanban, flowchart, slide deck, report) in a Nexus-UI workbench and return a shareable URL.')]
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

        // REQ-M5-002: look up the workbench by slug BEFORE validating so the
        // report schema can cross-reference embed targets against the active
        // workbench_id. For brand-new workbenches $existing is null and any
        // embed is rejected as cross-workbench (a fresh workbench has no
        // snapshots to embed yet). No workbench row is created until after
        // the validation gate — so validation failures leave no side effects.
        $slug = (string) $request->get('workbench_slug');
        $callerId = Auth::id();

        // REQ-M6-005: a soft-deleted workbench is unreachable — MCP writes
        // must not silently resurrect it nor collide with its unique slug by
        // creating a new row. Return a clear error; the owner has to restore
        // the workbench before agents can write again.
        $trashed = Workbench::withTrashed()->where('slug', $slug)->first();

        if ($trashed !== null && $trashed->trashed()) {
            return Response::error("Workbench '{$slug}' has been deleted and cannot be written to. Restore it to resume writes.");
        }

        $existing = $trashed;

        // REQ-M1-008: run the view-specific schema validator BEFORE any
        // workbench/snapshot rows are created so invalid payloads never
        // leak side effects. Surfaces the validator's dot-path error
        // message as an MCP tool error.
        try {
            $dataPayload = $this->validateDataPayload($viewType, $dataPayload, $existing?->id);
        } catch (
            TableViewSchemaException
            |SlideDeckViewSchemaException
            |KanbanViewSchemaException
            |FlowchartViewSchemaException
            |ReportViewSchemaException $exception
        ) {
            return Response::error($exception->getMessage());
        }

        // REQ-M4-000: capture the authenticated Sanctum user as the owner on
        // first workbench creation. Unauthenticated callers (local stdio) leave
        // owner_user_id null — such workbenches are system-owned and never
        // shareable (REQ-M4-005). Ownership is set once on create and is never
        // reassigned by this tool on subsequent calls.
        //
        // REQ-M4-008: writes are owner-only. An existing workbench can only be
        // written to by its owner (or, for system-owned workbenches, by local
        // stdio — i.e. unauthenticated — callers). Shares grant read access
        // only; they never grant write access.

        if ($existing !== null) {
            if ($existing->owner_user_id !== null && (int) $existing->owner_user_id !== (int) $callerId) {
                return Response::error('This workbench is owned by another user; MCP writes are owner-only.');
            }

            if ($existing->owner_user_id === null && $callerId !== null) {
                return Response::error('This workbench is system-owned (local stdio) and cannot be written to by authenticated users.');
            }

            $workbench = $existing;
        } else {
            $workbench = Workbench::query()->create([
                'slug' => $slug,
                'name' => Str::headline($slug),
                'owner_user_id' => $callerId,
            ]);
        }

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
                ->description('Renderer to use for the payload (table, kanban, flowchart, slide_deck, report).')
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
     * @throws ReportViewSchemaException
     */
    private function validateDataPayload(string $viewType, array $payload, ?int $workbenchId = null): array
    {
        return match ($viewType) {
            'table' => TableViewSchema::validate($payload),
            'slide_deck' => SlideDeckViewSchema::validate($payload),
            'kanban' => KanbanViewSchema::validate($payload),
            'flowchart' => FlowchartViewSchema::validate($payload),
            'report' => ReportViewSchema::validate($payload, $workbenchId),
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
