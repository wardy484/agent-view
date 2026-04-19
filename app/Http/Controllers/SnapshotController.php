<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Snapshot;
use App\Models\SnapshotVersion;
use App\Models\Workbench;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class SnapshotController extends Controller
{
    public function show(Workbench $workbench, Snapshot $snapshot): Response|HttpResponse
    {
        if ($snapshot->workbench_id !== $workbench->id) {
            abort(404);
        }

        $version = $snapshot->currentVersion()->first()
            ?? $snapshot->versions()->orderByDesc('revision')->first();

        if (! $version instanceof SnapshotVersion) {
            abort(404);
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
            'version' => [
                'id' => $version->id,
                'revision' => $version->revision,
                'view_type' => $version->view_type,
                'data_payload' => $version->data_payload,
                'metadata' => $version->metadata,
            ],
        ]);
    }
}
