<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Snapshot;
use App\Models\SnapshotVersion;
use App\Models\Workbench;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class SnapshotController extends Controller
{
    public function show(Request $request, Workbench $workbench, Snapshot $snapshot): Response|HttpResponse
    {
        if ($snapshot->workbench_id !== $workbench->id) {
            abort(404);
        }

        // REQ-M1-007: list every revision newest first so the React switcher
        // can render and link to each one.
        $versions = $snapshot->versions()
            ->reorder('revision', 'desc')
            ->get(['id', 'revision', 'view_type', 'created_at']);

        if ($versions->isEmpty()) {
            abort(404);
        }

        $version = $this->resolveActiveVersion($request, $snapshot, $versions);

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
            'versions' => $versions->map(fn (SnapshotVersion $candidate): array => [
                'id' => $candidate->id,
                'revision' => $candidate->revision,
                'view_type' => $candidate->view_type,
                'created_at' => $candidate->created_at?->toIso8601String(),
                'is_current' => $candidate->id === $version->id,
            ])->values()->all(),
            'mode' => $mode,
            'isAuthenticated' => $isAuthenticated,
        ]);
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
