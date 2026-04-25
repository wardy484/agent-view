<?php

declare(strict_types=1);

namespace App\Http\Controllers\Snapshots;

use App\Enums\CommentAuthorKind;
use App\Enums\CommentKind;
use App\Enums\CommentStatus;
use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Snapshot;
use App\Policies\CommentPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * REQ-M6-017: HTTP path for posting a reply to a root comment from the
 * sidebar Reply box. Replies inherit the root's `block_id` and have no
 * anchor of their own — they're a thread, not anchored content. Author
 * is always the authenticated user, kind is always `comment` (suggestions
 * cannot nest), status is always `open`.
 *
 * Authorisation reuses {@see CommentPolicy::create()} via the
 * `Gate::authorize('create', [Comment::class, $snapshot])` shape — the same
 * predicate that already gates root comment creation; if you can comment on
 * the snapshot, you can reply to a comment on it.
 */
class CommentReplyController extends Controller
{
    public function store(Request $request, Snapshot $snapshot, Comment $comment): RedirectResponse
    {
        if ($comment->snapshot_id !== $snapshot->id) {
            abort(404);
        }

        if ($comment->parent_comment_id !== null) {
            // REQ-M6-006: replies don't nest. The UI never offers a Reply
            // affordance on a reply, but a stale tab could still POST.
            abort(422, 'Cannot reply to a reply.');
        }

        Gate::authorize('create', [Comment::class, $snapshot]);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        Comment::query()->create([
            'snapshot_id' => $snapshot->id,
            'block_id' => $comment->block_id,
            'parent_comment_id' => $comment->id,
            'kind' => CommentKind::Comment->value,
            'body' => $validated['body'],
            'proposed_text' => null,
            'anchor_quote' => null,
            'anchor_prefix' => null,
            'anchor_suffix' => null,
            'anchor_start_hint' => null,
            'anchor_end_hint' => null,
            'status' => CommentStatus::Open->value,
            'resolution' => null,
            'created_on_version_id' => $snapshot->current_version_id,
            'addressed_on_version_id' => null,
            'author_user_id' => $request->user()->id,
            'author_kind' => CommentAuthorKind::User->value,
        ]);

        return back();
    }
}
