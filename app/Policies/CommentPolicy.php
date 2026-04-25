<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CommentAuthorKind;
use App\Models\Comment;
use App\Models\Snapshot;
use App\Models\User;

/**
 * REQ-M6-005: authorisation rules for inline review comments.
 *
 *  - view/react mirror {@see SnapshotPolicy::view()} exactly: anyone who can
 *    read the report can read its comments and add reactions.
 *  - create requires snapshot ownership OR an unrevoked `snapshot_shares`
 *    row matching the user's id or email (sharing-by-email parity with
 *    {@see SnapshotPolicy::view()}'s shared-mode branch).
 *  - update/delete require ownership of the comment (`author_user_id`) OR
 *    of the snapshot, AND the comment must be human-authored. Agent rows
 *    (`author_kind = agent`) are immutable history.
 *  - resolve loosens the immutability rule: the snapshot owner (or the
 *    original human author) can resolve any thread, including agent
 *    auto-replies.
 */
class CommentPolicy
{
    public function __construct(private readonly SnapshotPolicy $snapshotPolicy) {}

    /**
     * REQ-M6-005: view mirrors SnapshotPolicy@view.
     */
    public function view(User $user, Comment $comment): bool
    {
        return $this->snapshotPolicy->view($user, $comment->snapshot);
    }

    /**
     * REQ-M6-005: snapshot owner OR an active share grantee may root a
     * comment thread. Reuses the shared-by-email parity from M4.
     */
    public function create(User $user, Snapshot $snapshot): bool
    {
        if ($this->ownsSnapshot($user, $snapshot)) {
            return true;
        }

        return $this->hasActiveShare($user, $snapshot);
    }

    /**
     * REQ-M6-005: editable iff human-authored AND caller is the original
     * author or the snapshot owner.
     */
    public function update(User $user, Comment $comment): bool
    {
        if ($comment->author_kind !== CommentAuthorKind::User) {
            return false;
        }

        return $this->isAuthorOrOwner($user, $comment);
    }

    /**
     * REQ-M6-005: delete follows update's rules verbatim.
     */
    public function delete(User $user, Comment $comment): bool
    {
        return $this->update($user, $comment);
    }

    /**
     * REQ-M6-005: snapshot owner can resolve any thread (agent or human);
     * the original human author can resolve their own thread.
     */
    public function resolve(User $user, Comment $comment): bool
    {
        if ($this->ownsSnapshot($user, $comment->snapshot)) {
            return true;
        }

        return $comment->author_kind === CommentAuthorKind::User
            && $comment->author_user_id !== null
            && (int) $user->id === (int) $comment->author_user_id;
    }

    /**
     * REQ-M6-005: react mirrors view — any reader can leave a reaction.
     */
    public function react(User $user, Comment $comment): bool
    {
        return $this->view($user, $comment);
    }

    /**
     * REQ-M6-008: acceptance of a suggestion is strict owner-only — only the
     * snapshot owner can replace block content via the accept flow. Even the
     * suggestion's original author cannot accept their own suggestion.
     */
    public function accept(User $user, Comment $comment): bool
    {
        return $this->ownsSnapshot($user, $comment->snapshot);
    }

    private function ownsSnapshot(User $user, Snapshot $snapshot): bool
    {
        $ownerId = $snapshot->workbench?->owner_user_id
            ?? $snapshot->loadMissing('workbench')->workbench?->owner_user_id;

        return $ownerId !== null && (int) $user->id === (int) $ownerId;
    }

    /**
     * REQ-M6-005: replicate SnapshotPolicy@view's shared-mode predicate so
     * sharing-by-email keeps working for comment writes too.
     */
    private function hasActiveShare(User $user, Snapshot $snapshot): bool
    {
        return $snapshot->shares()
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->orWhere('email', strtolower((string) $user->email));
            })
            ->exists();
    }

    private function isAuthorOrOwner(User $user, Comment $comment): bool
    {
        if ($comment->author_user_id !== null && (int) $user->id === (int) $comment->author_user_id) {
            return true;
        }

        return $this->ownsSnapshot($user, $comment->snapshot);
    }
}
