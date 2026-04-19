<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Snapshot;
use App\Models\Workbench;
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
    public function handle(Request $request): ResponseFactory
    {
        $workbench = Workbench::query()->firstOrCreate(
            ['slug' => $request->get('workbench_slug')],
            ['name' => Str::headline((string) $request->get('workbench_slug'))],
        );

        $snapshot = $this->resolveSnapshot($request, $workbench);

        $version = SnapshotVersioning::append(
            snapshot: $snapshot,
            viewType: (string) $request->get('view_type'),
            dataPayload: (array) $request->get('data_payload'),
            metadata: $request->get('metadata') ? (array) $request->get('metadata') : null,
            previewHtml: $this->stubPreviewHtml($snapshot),
        );

        $snapshot->forceFill(['current_version_id' => $version->id])->save();

        $url = route('workbench.snapshot.show', [
            'workbench' => $workbench->slug,
            'snapshot' => $snapshot->slug,
        ]);

        return Response::make([
            // Text content part: the workbench URL (REQ-M1-004).
            Response::text($url),

            // text/html resource content part (REQ-M1-004).
            Response::embeddedResource($url, 'text/html', (string) $version->preview_html),
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

    private function stubPreviewHtml(Snapshot $snapshot): string
    {
        // The real renderer arrives in REQ-M1-011; for now emit a small stub
        // so the text/html resource part has a body.
        return '<section data-snapshot="'.e($snapshot->slug).'"></section>';
    }
}
