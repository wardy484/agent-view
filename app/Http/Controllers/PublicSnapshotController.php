<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SnapshotVisibility;
use App\Models\Snapshot;
use App\Models\SnapshotShareAccess;
use App\Models\SnapshotVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * REQ-M4-002: public read-only renderer for snapshots shared via `visibility=link`.
 *
 * The route is throttled (see `routes/web.php`) and every successful hit writes
 * a row to `snapshot_share_accesses` for the owner's audit trail (REQ-M4-007
 * will surface it in the UI). Viewers always see the snapshot's latest revision
 * — earlier revisions remain hidden because we hardwire `current_version_id`
 * and never honour `?revision=`.
 */
class PublicSnapshotController extends Controller
{
    public function show(Request $request, string $token): Response|HttpResponse
    {
        $snapshot = Snapshot::query()
            ->where('share_token', $token)
            ->where('visibility', SnapshotVisibility::Link->value)
            ->first();

        if ($snapshot === null) {
            abort(404);
        }

        $version = $snapshot->currentVersion;

        if (! $version instanceof SnapshotVersion) {
            abort(404);
        }

        // REQ-M4-002: audit every view. Write best-effort — a logging failure
        // must never prevent the viewer from reading the snapshot.
        try {
            SnapshotShareAccess::query()->create([
                'snapshot_id' => $snapshot->id,
                'share_token' => $token,
                'user_id' => $request->user()?->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable) {
            // swallow — audit is non-critical to the read path.
        }

        return Inertia::render('snapshot', [
            'workbench' => [
                'slug' => $snapshot->workbench->slug,
                'name' => $snapshot->workbench->name,
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
            // REQ-M4-006: the page component consumes these flags to hide all
            // mutation affordances, the version switcher, and the "Send back
            // to Agent" button. A public-link viewer is never the owner.
            'versions' => [],
            'is_owner' => false,
            'is_public_link' => true,
        ]);
    }
}
