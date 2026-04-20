<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SnapshotVisibility;
use App\Models\Snapshot;
use App\Models\SnapshotShare;
use App\Models\Workbench;
use App\Nexus\ShareSnapshotByEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * REQ-M4-010: owner-only mutation endpoints for the Share dialog.
 *
 *  - updateVisibility — flip `private | link | shared` (and mint the token
 *    via {@see Snapshot::setVisibility()} when entering link mode).
 *  - rotateToken      — mint a fresh share_token; invalidates the old URL.
 *  - storeShare       — invite a new email via {@see ShareSnapshotByEmail}.
 *  - destroyShare     — soft-revoke an existing {@see SnapshotShare}.
 *
 * Every action asserts the caller owns the workbench and that the snapshot
 * belongs to that workbench. Non-owners get 403; unauthenticated get 401.
 */
class SnapshotShareController extends Controller
{
    public function __construct(private readonly ShareSnapshotByEmail $shareByEmail) {}

    public function updateVisibility(Request $request, Workbench $workbench, Snapshot $snapshot): RedirectResponse
    {
        $this->authorizeOwner($request, $workbench, $snapshot);

        $data = $request->validate([
            'visibility' => ['required', Rule::enum(SnapshotVisibility::class)],
        ]);

        $snapshot->setVisibility(SnapshotVisibility::from($data['visibility']));

        return back();
    }

    public function rotateToken(Request $request, Workbench $workbench, Snapshot $snapshot): RedirectResponse
    {
        $this->authorizeOwner($request, $workbench, $snapshot);

        // REQ-M4-009: force a fresh token even if we were already Link. We run
        // through Private → Link to guarantee the old token is revoked.
        $snapshot->setVisibility(SnapshotVisibility::Private);
        $snapshot->refresh();
        $snapshot->setVisibility(SnapshotVisibility::Link);

        return back();
    }

    public function storeShare(Request $request, Workbench $workbench, Snapshot $snapshot): RedirectResponse
    {
        $this->authorizeOwner($request, $workbench, $snapshot);

        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
        ]);

        // Upsert: if an active share already exists for this email, do nothing.
        // If a revoked share exists, un-revoke it instead of creating a new row.
        $normalised = strtolower(trim($data['email']));

        $existing = SnapshotShare::query()
            ->where('snapshot_id', $snapshot->id)
            ->where('email', $normalised)
            ->first();

        if ($existing instanceof SnapshotShare) {
            if ($existing->revoked_at !== null) {
                $existing->forceFill(['revoked_at' => null])->save();
            }

            return back();
        }

        $this->shareByEmail->share($snapshot, $normalised, $request->user());

        return back();
    }

    public function destroyShare(Request $request, Workbench $workbench, Snapshot $snapshot, SnapshotShare $share): RedirectResponse
    {
        $this->authorizeOwner($request, $workbench, $snapshot);

        if ($share->snapshot_id !== $snapshot->id) {
            abort(404);
        }

        if ($share->revoked_at === null) {
            $share->revoke();
        }

        return back();
    }

    private function authorizeOwner(Request $request, Workbench $workbench, Snapshot $snapshot): void
    {
        if ($snapshot->workbench_id !== $workbench->id) {
            abort(404);
        }

        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        if ((int) $user->id !== (int) $workbench->owner_user_id) {
            abort(403);
        }
    }
}
