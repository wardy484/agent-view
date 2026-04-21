<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Workbench;

/**
 * REQ-M6-001: sole authority for organisational mutations on a workbench.
 *
 * Every ability returns true only when the caller is authenticated and is
 * the workbench's owner. Workbenches with `owner_user_id = null` (local
 * stdio / system-created per REQ-M4-000) are never organisable — no user
 * is allowed to rename / pin / archive / delete them.
 *
 * Read access to a workbench's snapshots remains governed by
 * {@see SnapshotPolicy}; this policy is only about the seven organisational
 * actions enumerated in the spec.
 */
class WorkbenchPolicy
{
    public function rename(?User $user, Workbench $workbench): bool
    {
        return $this->ownsWorkbench($user, $workbench);
    }

    public function pin(?User $user, Workbench $workbench): bool
    {
        return $this->ownsWorkbench($user, $workbench);
    }

    public function unpin(?User $user, Workbench $workbench): bool
    {
        return $this->ownsWorkbench($user, $workbench);
    }

    public function archive(?User $user, Workbench $workbench): bool
    {
        return $this->ownsWorkbench($user, $workbench);
    }

    public function unarchive(?User $user, Workbench $workbench): bool
    {
        return $this->ownsWorkbench($user, $workbench);
    }

    public function delete(?User $user, Workbench $workbench): bool
    {
        return $this->ownsWorkbench($user, $workbench);
    }

    public function restore(?User $user, Workbench $workbench): bool
    {
        return $this->ownsWorkbench($user, $workbench);
    }

    private function ownsWorkbench(?User $user, Workbench $workbench): bool
    {
        if ($user === null) {
            return false;
        }

        $ownerId = $workbench->owner_user_id;

        if ($ownerId === null) {
            return false;
        }

        return (int) $user->id === (int) $ownerId;
    }
}
