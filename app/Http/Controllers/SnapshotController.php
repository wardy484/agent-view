<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Snapshot;
use App\Models\SnapshotVersion;
use App\Models\Workbench;
use App\Policies\SnapshotPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SnapshotController extends Controller
{
    public function __construct(private readonly SnapshotPolicy $policy) {}

    public function show(Request $request, Workbench $workbench, Snapshot $snapshot): Response|HttpResponse
    {
        if ($snapshot->workbench_id !== $workbench->id) {
            abort(404);
        }

        // REQ-M4-005: gate on the snapshot policy — owner or unrevoked share
        // row. Eager-load the workbench so the policy skips an extra query.
        $snapshot->setRelation('workbench', $workbench);

        if (! $this->policy->view($request->user(), $snapshot)) {
            abort($request->user() === null ? 401 : 403);
        }

        // REQ-M1-007: list every revision newest first so the React switcher
        // can render and link to each one.
        $versions = $snapshot->versions()
            ->reorder('revision', 'desc')
            ->get(['id', 'revision', 'view_type', 'created_at']);

        if ($versions->isEmpty()) {
            abort(404);
        }

        // REQ-M4-006: only the workbench owner may browse the full revision
        // history or jump to an arbitrary revision. Shared-with viewers always
        // see the latest revision and no version switcher.
        $isOwner = $request->user() !== null
            && (int) $request->user()->id === (int) $workbench->owner_user_id;

        $version = $isOwner
            ? $this->resolveActiveVersion($request, $snapshot, $versions)
            : $this->latestVersion($snapshot, $versions);

        $isAuthenticated = $request->user() !== null;

        // REQ-M3-010: ?mode=preview forces bare rendering; guests always get
        // preview mode so shared links look polished without an account.
        $mode = $request->query('mode') === 'preview' || ! $isAuthenticated
            ? 'preview'
            : 'app';

        // REQ-M5-007: report view_types resolve their embed blocks server-side
        // so the React renderer doesn't fan out N HTTP calls. Each embed is
        // gated through SnapshotPolicy@view (REQ-M5-004 transitive read).
        $versionPayload = [
            'id' => $version->id,
            'revision' => $version->revision,
            'view_type' => $version->view_type,
            'data_payload' => $version->data_payload,
            'metadata' => $version->metadata,
        ];

        if ($version->view_type === 'report') {
            $versionPayload['resolved_blocks'] = $this->resolveReportBlocks($request, $version);
        }

        return Inertia::render('snapshot', [
            'workbench' => [
                'slug' => $workbench->slug,
                'name' => $workbench->name,
            ],
            'snapshot' => [
                'id' => $snapshot->id,
                'slug' => $snapshot->slug,
                'title' => $snapshot->title,
                'current_version_id' => $snapshot->current_version_id,
            ],
            'version' => $versionPayload,
            // REQ-M4-006: non-owners never see the full revision history — the
            // React switcher is hidden, and we don't leak sibling revisions.
            'versions' => $isOwner
                ? $versions->map(fn (SnapshotVersion $candidate): array => [
                    'id' => $candidate->id,
                    'revision' => $candidate->revision,
                    'view_type' => $candidate->view_type,
                    'created_at' => $candidate->created_at?->toIso8601String(),
                    'is_current' => $candidate->id === $version->id,
                ])->values()->all()
                : [],
            'mode' => $mode,
            'isAuthenticated' => $isAuthenticated,
            // REQ-M4-006: the page component reads these flags to hide the
            // version switcher, the "Send back to Agent" control, and every
            // mutation affordance (rename/delete/re-share) from non-owners.
            'is_owner' => $isOwner,
            'is_public_link' => false,
        ]);
    }

    /**
     * REQ-M5-007: walk the report's blocks[] in order and inline each
     * one for the React renderer. Markdown blocks pass through; embed
     * blocks load the pinned snapshot version (from snapshot_embeds for
     * THIS report version) and inline the embedded view's payload.
     *
     * Each embed is checked against the snapshot policy — REQ-M5-004 grants
     * transitive read to the caller, but if it ever returns false the embed
     * collapses to `{restricted: true}` so the page still renders.
     *
     * @return list<array<string, mixed>>
     */
    private function resolveReportBlocks(Request $request, SnapshotVersion $version): array
    {
        $payload = $version->data_payload ?? [];
        $blocks = is_array($payload['blocks'] ?? null) ? array_values($payload['blocks']) : [];

        if ($blocks === []) {
            return [];
        }

        // Pull every pinned embed for this report revision in one query so
        // we can index by block_index without N+1 lookups.
        $pins = DB::table('snapshot_embeds')
            ->where('report_version_id', $version->id)
            ->get(['block_index', 'embedded_snapshot_id', 'embedded_version_id'])
            ->keyBy('block_index');

        $embeddedVersionIds = $pins->pluck('embedded_version_id')->all();

        $pinnedVersions = $embeddedVersionIds === []
            ? collect()
            : SnapshotVersion::query()
                ->whereIn('id', $embeddedVersionIds)
                ->with(['snapshot.workbench', 'snapshot.currentVersion:id,revision'])
                ->get()
                ->keyBy('id');

        $resolved = [];

        foreach ($blocks as $index => $block) {
            $type = is_string($block['type'] ?? null) ? $block['type'] : '';

            if ($type === 'markdown') {
                $resolved[] = [
                    'type' => 'markdown',
                    'body' => is_string($block['body'] ?? null) ? $block['body'] : '',
                ];

                continue;
            }

            if ($type !== 'embed') {
                continue;
            }

            $snapshotId = is_int($block['snapshot_id'] ?? null) ? (int) $block['snapshot_id'] : null;
            $pin = $pins->get($index);
            $pinnedVersion = $pin === null ? null : $pinnedVersions->get($pin->embedded_version_id);
            $pinnedSnapshot = $pinnedVersion?->snapshot;

            if ($pinnedSnapshot === null || $pinnedVersion === null) {
                $resolved[] = [
                    'type' => 'embed',
                    'snapshot_id' => $snapshotId,
                    'restricted' => true,
                ];

                continue;
            }

            // REQ-M5-005: even though the report owner could embed it,
            // re-check the policy for the current viewer in case future
            // policy changes diverge ownership and embed visibility.
            if (! $this->policy->view($request->user(), $pinnedSnapshot)) {
                $resolved[] = [
                    'type' => 'embed',
                    'snapshot_id' => (int) $pinnedSnapshot->id,
                    'restricted' => true,
                ];

                continue;
            }

            $currentRevision = $pinnedSnapshot->currentVersion?->revision ?? $pinnedVersion->revision;

            $resolved[] = [
                'type' => 'embed',
                'snapshot_id' => (int) $pinnedSnapshot->id,
                'snapshot_slug' => $pinnedSnapshot->slug,
                'workbench_slug' => $pinnedSnapshot->workbench?->slug,
                'title' => $pinnedSnapshot->title ?? $pinnedSnapshot->slug,
                'view_type' => $pinnedVersion->view_type,
                'pinned_version_id' => (int) $pinnedVersion->id,
                'pinned_revision' => (int) $pinnedVersion->revision,
                'current_revision' => (int) $currentRevision,
                'is_stale' => (int) $currentRevision !== (int) $pinnedVersion->revision,
                'data_payload' => $pinnedVersion->data_payload,
            ];
        }

        return $resolved;
    }

    private function latestVersion(Snapshot $snapshot, $versions): SnapshotVersion
    {
        $activeId = $snapshot->current_version_id ?? $versions->first()->id;

        return $snapshot->versions()->whereKey($activeId)->firstOrFail();
    }

    /**
     * Resolve the active version: an explicit `?revision=` wins, otherwise
     * fall back to `current_version_id`, then to the latest revision.
     */
    private function resolveActiveVersion(Request $request, Snapshot $snapshot, $versions): SnapshotVersion
    {
        $requestedRevision = $request->query('revision');

        if ($requestedRevision !== null && ctype_digit((string) $requestedRevision)) {
            $match = $versions->firstWhere('revision', (int) $requestedRevision);

            if (! $match instanceof SnapshotVersion) {
                abort(404);
            }

            return $snapshot->versions()->whereKey($match->id)->firstOrFail();
        }

        $activeId = $snapshot->current_version_id ?? $versions->first()->id;

        return $snapshot->versions()->whereKey($activeId)->firstOrFail();
    }
}
