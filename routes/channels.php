<?php

use App\Models\Snapshot;
use App\Models\User;
use App\Policies\SnapshotPolicy;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * REQ-M7-002: private channel `snapshot.{snapshot_id}` for live revision
 * broadcasts. Authorisation MUST mirror SnapshotPolicy@view for owner + active
 * snapshot_shares grantees only — link-token viewers cannot subscribe (the
 * token path requires HTTP request context, which channel auth does not
 * provide, and exposing live websocket pushes via a static URL would be a
 * leak).
 */
Broadcast::channel('snapshot.{snapshotId}', function (User $user, int $snapshotId): bool {
    $snapshot = Snapshot::query()->with('workbench')->find($snapshotId);

    if ($snapshot === null) {
        return false;
    }

    // Pass `token: null` deliberately — link-token viewers must not be granted
    // a private channel subscription (REQ-M7-002).
    return app(SnapshotPolicy::class)->view($user, $snapshot, null);
});
