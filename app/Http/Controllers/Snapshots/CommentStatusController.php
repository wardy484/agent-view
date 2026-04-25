<?php

declare(strict_types=1);

namespace App\Http\Controllers\Snapshots;

use App\Enums\CommentResolution;
use App\Enums\CommentStatus;
use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Snapshot;
use App\Policies\CommentPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * REQ-M6-017: HTTP path for the sidebar's status dropdown. Flips a comment
 * between `open`, `resolved`, and `wontfix` from a click. Authorisation:
 *  - resolved/open use {@see CommentPolicy::resolve()}: snapshot
 *    owner OR original human author.
 *  - wontfix uses {@see CommentPolicy::update()}: human author
 *    or snapshot owner of a non-agent comment. Stronger gate because
 *    "won't fix" is editorial dismissal, not just closing the loop.
 *
 * Resolving sets `resolution = user` (vs the agent path setting `agent`);
 * re-opening clears it. The model's `static::updated` hook bumps the
 * snapshot's `comments_revision` automatically (REQ-M6-016).
 */
class CommentStatusController extends Controller
{
    public function update(Request $request, Snapshot $snapshot, Comment $comment): RedirectResponse
    {
        if ($comment->snapshot_id !== $snapshot->id) {
            abort(404);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in([
                CommentStatus::Open->value,
                CommentStatus::Resolved->value,
                CommentStatus::Wontfix->value,
            ])],
        ]);

        $next = CommentStatus::from($validated['status']);

        Gate::authorize($next === CommentStatus::Wontfix ? 'update' : 'resolve', $comment);

        $comment->status = $next;
        $comment->resolution = $next === CommentStatus::Resolved
            ? CommentResolution::User
            : null;
        $comment->save();

        return back();
    }
}
