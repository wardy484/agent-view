<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SnapshotVersion;
use App\Models\Workbench;
use App\Nexus\AgentActivityDashboard;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * REQ-M3-007: the dashboard is rendered by `snapshot.tsx` — there is no
 * bespoke page. We refresh the singleton snapshot on every visit and then
 * render the standard snapshot Inertia component.
 */
class AgentActivityController extends Controller
{
    public function show(Request $request, Workbench $workbench): Response
    {
        $version = AgentActivityDashboard::refresh($workbench);

        $snapshot = $version->snapshot;

        $versions = $snapshot->versions()
            ->reorder('revision', 'desc')
            ->get(['id', 'revision', 'view_type', 'created_at']);

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
        ]);
    }
}
