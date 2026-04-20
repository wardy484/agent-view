<?php

declare(strict_types=1);

namespace App\Nexus;

use App\Actions\Fortify\CreateNewUser;
use App\Models\Snapshot;
use App\Models\SnapshotShare;
use App\Models\User;
use App\Notifications\SnapshotInvitationNotification;
use App\Notifications\SnapshotSharedNotification;
use Illuminate\Support\Facades\Notification;

/**
 * REQ-M4-004: the sole entry point for granting snapshot view access by email.
 *
 * - If the email matches an existing {@see User}, the row is created with
 *   `user_id` populated and a {@see SnapshotSharedNotification} is queued.
 * - If no matching user exists, the row is created with `user_id = null` and
 *   a {@see SnapshotInvitationNotification} is sent on-demand to the email.
 *   Registration back-fills the `user_id` via
 *   {@see CreateNewUser::create()}.
 */
class ShareSnapshotByEmail
{
    public function share(Snapshot $snapshot, string $email, User $grantedBy): SnapshotShare
    {
        $normalised = strtolower(trim($email));
        $invitee = User::query()->where('email', $normalised)->first();

        $share = SnapshotShare::query()->create([
            'snapshot_id' => $snapshot->id,
            'email' => $normalised,
            'user_id' => $invitee?->id,
            'granted_by_user_id' => $grantedBy->id,
        ]);

        if ($invitee !== null) {
            $invitee->notify(new SnapshotSharedNotification($snapshot));
        } else {
            Notification::route('mail', $normalised)
                ->notify(new SnapshotInvitationNotification($snapshot));
        }

        return $share;
    }
}
