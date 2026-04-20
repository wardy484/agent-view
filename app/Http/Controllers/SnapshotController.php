<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Snapshot;
use App\Models\SnapshotVersion;
use App\Models\Workbench;
use App\Policies\SnapshotPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
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
            'version' => [
                'id' => $version->id,
                'revision' => $version->revision,
                'view_type' => $version->view_type,
                'data_payload' => $version->data_payload,
                'metadata' => $version->metadata,
            ],
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
