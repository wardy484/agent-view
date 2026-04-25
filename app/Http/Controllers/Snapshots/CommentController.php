<?php

declare(strict_types=1);

namespace App\Http\Controllers\Snapshots;

use App\Enums\CommentAuthorKind;
use App\Enums\CommentKind;
use App\Enums\CommentStatus;
use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Snapshot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * REQ-M6-014: snapshot owner / share grantee creates a root review comment
 * anchored to a markdown block. The route is the only path the React review
 * UI uses to file new threads — replies and resolutions ride on dedicated
 * MCP tools (REQ-M6-010 / REQ-M6-011) or M6-015..018 endpoints.
 */
class CommentController extends Controller
{
    public function store(Request $request, Snapshot $snapshot): RedirectResponse
    {
        Gate::authorize('create', [Comment::class, $snapshot]);

        $validated = $request->validate([
            'block_id' => ['required', 'string', 'uuid'],
            'kind' => ['required', 'in:comment,suggestion'],
            'body' => ['required', 'string', 'max:5000'],
            'proposed_text' => ['nullable', 'required_if:kind,suggestion', 'string', 'max:10000'],
            'anchor_quote' => ['required', 'string', 'max:2000'],
            'anchor_prefix' => ['nullable', 'string', 'max:200'],
            'anchor_suffix' => ['nullable', 'string', 'max:200'],
            'anchor_start_hint' => ['required', 'integer', 'min:0'],
            'anchor_end_hint' => ['required', 'integer', 'min:0'],
        ]);

        $snapshot->loadMissing('currentVersion');

        Comment::query()->create([
            'snapshot_id' => $snapshot->id,
            'block_id' => $validated['block_id'],
            'parent_comment_id' => null,
            'kind' => $validated['kind'] === 'suggestion'
                ? CommentKind::Suggestion->value
                : CommentKind::Comment->value,
            'body' => $validated['body'],
            'proposed_text' => $validated['proposed_text'] ?? null,
            'anchor_quote' => $validated['anchor_quote'],
            'anchor_prefix' => $validated['anchor_prefix'] ?? '',
            'anchor_suffix' => $validated['anchor_suffix'] ?? '',
            'anchor_start_hint' => $validated['anchor_start_hint'],
            'anchor_end_hint' => $validated['anchor_end_hint'],
            'status' => CommentStatus::Open->value,
            'resolution' => null,
            'created_on_version_id' => $snapshot->current_version_id,
            'addressed_on_version_id' => null,
            'author_user_id' => $request->user()->id,
            'author_kind' => CommentAuthorKind::User->value,
        ]);

        return redirect()->back();
    }
}
