<?php

declare(strict_types=1);

namespace App\Http\Controllers\Snapshots;

use App\Enums\CommentReactionEmoji;
use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Snapshot;
use App\Nexus\Comments\CommentReactionService;
use App\Policies\CommentPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * REQ-M6-017: HTTP path for the sidebar's reaction buttons. Toggles the
 * authenticated user's reaction with the given emoji on the given comment;
 * a second click with the same emoji removes it. Authorisation reuses
 * {@see CommentPolicy::react()} (any reader).
 */
class CommentReactionController extends Controller
{
    public function __construct(private readonly CommentReactionService $service) {}

    public function toggle(Request $request, Snapshot $snapshot, Comment $comment): RedirectResponse
    {
        if ($comment->snapshot_id !== $snapshot->id) {
            abort(404);
        }

        Gate::authorize('react', $comment);

        $validated = $request->validate([
            'emoji' => ['required', 'string', Rule::in(array_map(
                fn (CommentReactionEmoji $case): string => $case->value,
                CommentReactionEmoji::cases(),
            ))],
        ]);

        $this->service->toggle(
            $comment,
            $request->user(),
            CommentReactionEmoji::from($validated['emoji']),
        );

        return back();
    }
}
