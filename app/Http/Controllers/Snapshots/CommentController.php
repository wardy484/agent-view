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

        $kind = $request->input('kind');

        $validated = $request->validate([
            'block_id' => ['required', 'string', 'uuid'],
            'kind' => ['required', 'in:comment,suggestion'],
            // REQ-M6-032: suggestions no longer require a body. Pure
            // suggestions ship without a textarea (the pill composer collapses
            // to one field in suggestion mode), so the controller substitutes
            // `''` server-side. Comments still require a non-empty body.
            'body' => $kind === 'suggestion'
                ? ['nullable', 'string', 'max:5000']
                : ['required', 'string', 'max:5000'],
            // REQ-M6-031: an empty `proposed_text` represents deletion of the
            // selected text. Postgres' CHECK constraint only rejects NULL on
            // suggestions, so empty string is a valid sentinel. We accept
            // nullable here and substitute '' below when missing on a
            // suggestion (so the CHECK passes).
            'proposed_text' => ['nullable', 'string', 'max:10000'],
            'anchor_quote' => ['required', 'string', 'max:2000'],
            'anchor_prefix' => ['nullable', 'string', 'max:200'],
            'anchor_suffix' => ['nullable', 'string', 'max:200'],
            'anchor_start_hint' => ['required', 'integer', 'min:0'],
            'anchor_end_hint' => ['required', 'integer', 'min:0'],
            // REQ-M6-015: clients running on a historical revision may try to
            // submit a comment after the snapshot has been advanced. They send
            // the version_id they were rendering against; mismatch = 409 so
            // the UI can prompt the user to reload before retrying.
            'expected_version_id' => ['nullable', 'integer'],
        ]);

        $snapshot->loadMissing('currentVersion');

        // REQ-M6-015: belt-and-braces concurrency check. The UI hides the
        // composer in historical mode, but a stale tab could still POST.
        if (
            isset($validated['expected_version_id'])
            && $validated['expected_version_id'] !== null
            && $snapshot->current_version_id !== null
            && (int) $validated['expected_version_id'] !== (int) $snapshot->current_version_id
        ) {
            abort(409, 'Snapshot has been advanced since this comment was started.');
        }

        $isSuggestion = $validated['kind'] === 'suggestion';

        // REQ-M6-032: pure suggestions arrive with no body — substitute ''.
        $body = $validated['body'] ?? ($isSuggestion ? '' : '');
        // REQ-M6-031: an empty proposed_text on a suggestion = deletion.
        // The DB CHECK rejects NULL on suggestion, so coerce missing → ''.
        $proposedText = $isSuggestion
            ? ($validated['proposed_text'] ?? '')
            : ($validated['proposed_text'] ?? null);

        Comment::query()->create([
            'snapshot_id' => $snapshot->id,
            'block_id' => $validated['block_id'],
            'parent_comment_id' => null,
            'kind' => $isSuggestion
                ? CommentKind::Suggestion->value
                : CommentKind::Comment->value,
            'body' => $body,
            'proposed_text' => $proposedText,
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
