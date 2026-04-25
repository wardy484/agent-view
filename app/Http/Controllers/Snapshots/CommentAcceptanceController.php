<?php

declare(strict_types=1);

namespace App\Http\Controllers\Snapshots;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Snapshot;
use App\Nexus\Comments\StaleAnchorException;
use App\Nexus\Comments\SuggestionAcceptanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * REQ-M6-008: `POST /snapshots/{snapshot}/comments/{comment}/accept`.
 *
 * Owner-only. Resolves the comment's anchor against the snapshot's current
 * revision; on success patches the resolved range with the suggestion's
 * `proposed_text`, mints a new report revision, and atomically marks the
 * comment resolved (resolution = user). Stale anchors return 409. Returns
 * JSON to match the API-first shape of the M6 review tooling — the share
 * dialog uses Inertia redirects, but this endpoint will be invoked from
 * client-side review UI that wants the new revision id back inline.
 */
class CommentAcceptanceController extends Controller
{
    public function __construct(private readonly SuggestionAcceptanceService $service) {}

    public function __invoke(Request $request, Snapshot $snapshot, Comment $comment): JsonResponse
    {
        if ($comment->snapshot_id !== $snapshot->id) {
            abort(404);
        }

        Gate::authorize('accept', $comment);

        try {
            $newVersion = $this->service->accept($comment, $request->user());
        } catch (StaleAnchorException $exception) {
            return response()->json([
                'error' => 'stale_anchor',
                'reason' => $exception->reason,
            ], 409);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'error' => 'invalid_comment_kind',
                'reason' => $exception->getMessage(),
            ], 422);
        }

        $comment->refresh();

        return response()->json([
            'snapshot_version_id' => $newVersion->id,
            'revision' => $newVersion->revision,
            'comment' => [
                'id' => $comment->id,
                'status' => $comment->status->value,
                'resolution' => $comment->resolution?->value,
                'addressed_on_version_id' => $comment->addressed_on_version_id,
            ],
        ]);
    }
}
