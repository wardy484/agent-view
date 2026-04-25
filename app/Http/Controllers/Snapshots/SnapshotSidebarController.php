<?php

declare(strict_types=1);

namespace App\Http\Controllers\Snapshots;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SnapshotController;
use App\Models\Comment;
use App\Models\Snapshot;
use App\Models\SnapshotVersion;
use App\Nexus\Comments\CommentProjection;
use App\Policies\SnapshotPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * REQ-M6-016: `GET /snapshots/{snapshot}/sidebar` returns the projected
 * sidebar payload (comments + version history + revision pointers) shaped
 * exactly like the matching props on the Inertia page show endpoint, so the
 * frontend partial-reload swap is a drop-in replacement.
 *
 * The endpoint is hit every 8 seconds per active tab. To keep the cost
 * negligible it short-circuits with `no_change: true` when the caller
 * supplies a `?since=` cursor that matches the snapshot's
 * `comments_revision` AND the current revision pointer is unchanged. Every
 * Eloquent query eager-loads aggressively to avoid N+1 fan-out.
 */
class SnapshotSidebarController extends Controller
{
    public function __construct(private readonly SnapshotPolicy $policy) {}

    public function show(Request $request, Snapshot $snapshot): JsonResponse
    {
        // REQ-M6-016: gate via the same SnapshotPolicy@view used by the main
        // show endpoint. No token path here — the polling loop is initiated
        // by an authenticated session render, never by anonymous link views.
        $snapshot->loadMissing('workbench', 'currentVersion');

        if (! $this->policy->view($request->user(), $snapshot)) {
            abort($request->user() === null ? 401 : 403);
        }

        $sinceRaw = $request->query('since');
        $since = is_numeric($sinceRaw) ? (int) $sinceRaw : null;

        $currentRevision = (int) ($snapshot->currentVersion?->revision ?? 0);
        $currentVersionId = (int) ($snapshot->current_version_id ?? 0);
        $commentsRevision = (int) $snapshot->comments_revision;

        // Cheap short-circuit. The caller passes the last `comments_revision`
        // it observed; if neither the comments graph nor the active revision
        // has moved since then, return a tiny no-change body.
        if (
            $since !== null
            && $commentsRevision <= $since
            && (int) ($request->query('current_version_id') ?? $currentVersionId) === $currentVersionId
        ) {
            return new JsonResponse([
                'comments_revision' => $commentsRevision,
                'current_revision' => $currentRevision,
                'current_version_id' => $currentVersionId,
                'no_change' => true,
            ]);
        }

        // REQ-M6-014: comments are a report-only concern. Non-report views
        // get an empty payload but still drive the polling loop so the page
        // can react to a future view_type swap.
        $isReport = $snapshot->currentVersion?->view_type === 'report';

        // REQ-M6-015: a viewer is "historical" when an explicit ?revision=
        // resolved to anything other than the current pointer. The caller
        // forwards that flag so the sidebar resolves anchors against the
        // version it's actually rendering.
        $requestedRevisionRaw = $request->query('revision');
        $requestedRevision = is_numeric($requestedRevisionRaw) ? (int) $requestedRevisionRaw : null;
        $projectionVersion = $snapshot->currentVersion;
        $isHistoricalView = false;

        if ($requestedRevision !== null) {
            $match = $snapshot->versions()->where('revision', $requestedRevision)->first();
            if ($match !== null) {
                $projectionVersion = $match;
                $isHistoricalView = $currentVersionId !== 0 && (int) $match->id !== $currentVersionId;
            }
        }

        $comments = $isReport ? CommentProjection::forSnapshot($snapshot, $projectionVersion) : null;
        $versionHistory = $isReport
            ? $this->resolveVersionHistory($snapshot, $projectionVersion?->id ?? $currentVersionId)
            : null;

        return new JsonResponse([
            'comments_revision' => $commentsRevision,
            'current_revision' => $currentRevision,
            'current_version_id' => $currentVersionId,
            'no_change' => false,
            'is_historical_view' => $isHistoricalView,
            'comments' => $comments,
            'versionHistory' => $versionHistory,
        ]);
    }

    /**
     * Mirrors {@see SnapshotController::resolveVersionHistory()}
     * but tuned for the polling endpoint: we keep the version listing lean
     * and the addressed-comment fan-in to a single grouped query.
     *
     * @return list<array<string, mixed>>
     */
    private function resolveVersionHistory(Snapshot $snapshot, int $activeVersionId): array
    {
        /** @var Collection<int, SnapshotVersion> $versions */
        $versions = $snapshot->versions()
            ->reorder('revision', 'desc')
            ->get(['id', 'revision', 'view_type', 'metadata', 'created_at']);

        $byVersion = Comment::query()
            ->where('snapshot_id', $snapshot->id)
            ->whereNotNull('addressed_on_version_id')
            ->orderBy('id')
            ->get(['id', 'addressed_on_version_id', 'body', 'author_kind', 'status'])
            ->groupBy('addressed_on_version_id');

        return $versions
            ->map(function (SnapshotVersion $candidate) use ($byVersion, $activeVersionId): array {
                $metadata = is_array($candidate->metadata) ? $candidate->metadata : [];
                $summary = is_string($metadata['summary'] ?? null) ? (string) $metadata['summary'] : null;
                $authorKind = is_string($metadata['author_kind'] ?? null) ? (string) $metadata['author_kind'] : 'agent';

                $addressed = $byVersion->get($candidate->id, collect());

                return [
                    'id' => (int) $candidate->id,
                    'revision' => (int) $candidate->revision,
                    'view_type' => $candidate->view_type,
                    'author_kind' => $authorKind,
                    'summary' => $summary,
                    'is_current' => (int) $candidate->id === $activeVersionId,
                    'addressed_comment_ids' => $addressed
                        ->pluck('id')
                        ->map(fn ($id): int => (int) $id)
                        ->values()
                        ->all(),
                    'addressed_comments' => $addressed
                        ->map(fn (Comment $comment): array => [
                            'id' => (int) $comment->id,
                            'body_preview' => $this->commentBodyPreview((string) $comment->body),
                            'author_kind' => $comment->author_kind->value,
                            'status' => $comment->status->value,
                        ])
                        ->values()
                        ->all(),
                    'created_at' => $candidate->created_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    private function commentBodyPreview(string $body): string
    {
        $trimmed = trim($body);

        if ($trimmed === '') {
            return '';
        }

        if (mb_strlen($trimmed) <= 80) {
            return $trimmed;
        }

        return mb_substr($trimmed, 0, 79).'…';
    }
}
