<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SnapshotVisibility;
use App\Models\Comment;
use App\Models\Snapshot;
use App\Models\SnapshotShare;
use App\Models\SnapshotVersion;
use App\Models\Workbench;
use App\Nexus\Comments\CommentProjection;
use App\Nexus\Comments\CommentStaleUpdater;
use App\Policies\SnapshotPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SnapshotController extends Controller
{
    public function __construct(private readonly SnapshotPolicy $policy) {}

    public function show(Request $request, Workbench $workbench, Snapshot $snapshot): Response|HttpResponse
    {
        if ($snapshot->workbench_id !== $workbench->id) {
            abort(404);
        }

        // REQ-M4-005: gate on the snapshot policy — owner or unrevoked share
        // row. Eager-load the workbench so the policy skips an extra query.
        $snapshot->setRelation('workbench', $workbench);

        if (! $this->policy->view($request->user(), $snapshot)) {
            abort($request->user() === null ? 401 : 403);
        }

        // REQ-M1-007: list every revision newest first so the React switcher
        // can render and link to each one.
        $versions = $snapshot->versions()
            ->reorder('revision', 'desc')
            ->get(['id', 'revision', 'view_type', 'created_at']);

        if ($versions->isEmpty()) {
            abort(404);
        }

        // REQ-M4-006: only the workbench owner may browse the full revision
        // history or jump to an arbitrary revision. Shared-with viewers always
        // see the latest revision and no version switcher.
        $isOwner = $request->user() !== null
            && (int) $request->user()->id === (int) $workbench->owner_user_id;

        $activeVersionId = $isOwner
            ? $this->resolveActiveVersionId($request, $snapshot, $versions)
            : ($snapshot->current_version_id ?? $versions->first()->id);

        // Fetch the full row (with data_payload + metadata) for the active
        // version only — the listing collection is kept lean for the switcher.
        $version = $snapshot->versions()->whereKey($activeVersionId)->firstOrFail();

        $isAuthenticated = $request->user() !== null;

        // REQ-M3-010: ?mode=preview forces bare rendering; guests always get
        // preview mode so shared links look polished without an account.
        $mode = $request->query('mode') === 'preview' || ! $isAuthenticated
            ? 'preview'
            : 'app';

        // REQ-M5-007: report view_types resolve their embed blocks server-side
        // so the React renderer doesn't fan out N HTTP calls. Each embed is
        // gated through SnapshotPolicy@view (REQ-M5-004 transitive read).
        $versionPayload = [
            'id' => $version->id,
            'revision' => $version->revision,
            'view_type' => $version->view_type,
            'data_payload' => $version->data_payload,
            'metadata' => $version->metadata,
        ];

        // REQ-M6-014: comments + versionHistory props are only computed for
        // authenticated viewers on report views. Link-token viewers go through
        // PublicSnapshotController, which omits both props entirely.
        $comments = null;
        $versionHistory = null;

        if ($version->view_type === 'report') {
            $versionPayload['resolved_blocks'] = $this->resolveReportBlocks($request, $version);

            // REQ-M6-012: comments are scoped to markdown blocks on report
            // views, so the lazy stale updater only runs for that view_type.
            // The snapshot's currentVersion drives resolution — pin it to the
            // freshly-loaded relation so we don't issue an extra query.
            $snapshot->setRelation('currentVersion', $snapshot->current_version_id === $version->id
                ? $version
                : $snapshot->currentVersion);
            CommentStaleUpdater::syncStatusForRender($snapshot);

            if ($isAuthenticated) {
                // REQ-M6-014: serialise every root comment for the sidebar.
                // Re-uses the CommentProjection service the MCP tool consumes
                // so the agent-facing JSON and the UI prop stay in lockstep.
                $comments = CommentProjection::forSnapshot($snapshot, $snapshot->currentVersion);
                $versionHistory = $this->resolveVersionHistory($snapshot, $versions);
            }
        }

        return Inertia::render('snapshot', [
            'workbench' => [
                'slug' => $workbench->slug,
                'name' => $workbench->name,
            ],
            'snapshot' => [
                'id' => $snapshot->id,
                'slug' => $snapshot->slug,
                'title' => $snapshot->title,
                'current_version_id' => $snapshot->current_version_id,
            ],
            'version' => $versionPayload,
            // REQ-M4-006: non-owners never see the full revision history — the
            // React switcher is hidden, and we don't leak sibling revisions.
            'versions' => $isOwner
                ? $versions->map(fn (SnapshotVersion $candidate): array => [
                    'id' => $candidate->id,
                    'revision' => $candidate->revision,
                    'view_type' => $candidate->view_type,
                    'created_at' => $candidate->created_at?->toIso8601String(),
                    'is_current' => $candidate->id === $version->id,
                ])->values()->all()
                : [],
            'mode' => $mode,
            'isAuthenticated' => $isAuthenticated,
            // REQ-M4-006: the page component reads these flags to hide the
            // version switcher, the "Send back to Agent" control, and every
            // mutation affordance (rename/delete/re-share) from non-owners.
            'is_owner' => $isOwner,
            'is_public_link' => false,
            // REQ-M4-010: owner-only props that power the Share dialog. Empty
            // for non-owners so we never leak the guest list or the token.
            'visibility' => $snapshot->visibility?->value ?? SnapshotVisibility::Private->value,
            'share_url' => $isOwner && $snapshot->visibility === SnapshotVisibility::Link && $snapshot->share_token
                ? route('snapshot.public', ['token' => $snapshot->share_token])
                : null,
            'shares' => $isOwner
                ? $snapshot->shares()
                    ->orderBy('email')
                    ->get()
                    ->map(fn (SnapshotShare $share): array => [
                        'id' => $share->id,
                        'email' => $share->email,
                        'accepted' => $share->user_id !== null,
                        'created_at' => $share->created_at?->toIso8601String(),
                    ])
                    ->values()
                    ->all()
                : [],
            // REQ-M6-014: comments + versionHistory power the snapshot sidebar.
            // Both are null for non-report views and for unauthenticated link
            // viewers (consistent with REQ-M4-006 read-only stance).
            'comments' => $comments,
            'versionHistory' => $versionHistory,
        ]);
    }

    /**
     * REQ-M6-014: minimal version-history projection for the sidebar's History
     * tab. Each row carries the revision, view_type, author_kind, optional
     * `summary` from metadata, and the ids of every comment whose
     * `addressed_on_version_id` matches — the full History UI will render
     * these in a follow-up REQ. Order is newest-first to match the
     * version-switcher and the natural "what changed last" reading direction.
     *
     * @param  Collection<int, SnapshotVersion>  $versions
     * @return list<array<string, mixed>>
     */
    private function resolveVersionHistory(Snapshot $snapshot, Collection $versions): array
    {
        $byVersion = Comment::query()
            ->where('snapshot_id', $snapshot->id)
            ->whereNotNull('addressed_on_version_id')
            ->get(['id', 'addressed_on_version_id'])
            ->groupBy('addressed_on_version_id');

        // Pull metadata in one extra query keyed by version id — the listing
        // collection above purposely keeps its columns lean for the switcher.
        $metadataByVersion = SnapshotVersion::query()
            ->whereIn('id', $versions->pluck('id'))
            ->get(['id', 'metadata'])
            ->keyBy('id');

        return $versions
            ->map(function (SnapshotVersion $candidate) use ($byVersion, $metadataByVersion): array {
                $metadataRow = $metadataByVersion->get($candidate->id);
                $metadata = is_array($metadataRow?->metadata) ? $metadataRow->metadata : [];
                $summary = is_string($metadata['summary'] ?? null) ? (string) $metadata['summary'] : null;
                $authorKind = is_string($metadata['author_kind'] ?? null)
                    ? (string) $metadata['author_kind']
                    : 'agent';

                return [
                    'id' => (int) $candidate->id,
                    'revision' => (int) $candidate->revision,
                    'view_type' => $candidate->view_type,
                    'author_kind' => $authorKind,
                    'summary' => $summary,
                    'addressed_comment_ids' => $byVersion
                        ->get($candidate->id, collect())
                        ->pluck('id')
                        ->map(fn ($id): int => (int) $id)
                        ->values()
                        ->all(),
                    'created_at' => $candidate->created_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * REQ-M5-007: walk the report's blocks[] in order and inline each
     * one for the React renderer. Markdown blocks pass through; embed
     * blocks load the pinned snapshot version (from snapshot_embeds for
     * THIS report version) and inline the embedded view's payload.
     *
     * Each embed is checked against the snapshot policy — REQ-M5-004 grants
     * transitive read to the caller, but if it ever returns false the embed
     * collapses to `{restricted: true}` so the page still renders.
     *
     * @return list<array<string, mixed>>
     */
    private function resolveReportBlocks(Request $request, SnapshotVersion $version): array
    {
        $payload = $version->data_payload ?? [];
        $blocks = is_array($payload['blocks'] ?? null) ? array_values($payload['blocks']) : [];

        if ($blocks === []) {
            return [];
        }

        // REQ-M6-002: pull every pinned embed for this report revision in one
        // query so we can index by block_id (stable UUID) without N+1 lookups.
        $pins = DB::table('snapshot_embeds')
            ->where('report_version_id', $version->id)
            ->get(['block_id', 'embedded_snapshot_id', 'embedded_version_id'])
            ->keyBy('block_id');

        $embeddedVersionIds = $pins->pluck('embedded_version_id')->all();

        $pinnedVersions = $embeddedVersionIds === []
            ? collect()
            : SnapshotVersion::query()
                ->whereIn('id', $embeddedVersionIds)
                ->with(['snapshot.workbench', 'snapshot.currentVersion:id,revision'])
                ->get()
                ->keyBy('id');

        $resolved = [];

        foreach ($blocks as $block) {
            $type = is_string($block['type'] ?? null) ? $block['type'] : '';

            if ($type === 'markdown') {
                $resolved[] = [
                    'type' => 'markdown',
                    'body' => is_string($block['body'] ?? null) ? $block['body'] : '',
                ];

                continue;
            }

            if ($type !== 'embed') {
                continue;
            }

            $snapshotId = is_int($block['snapshot_id'] ?? null) ? (int) $block['snapshot_id'] : null;
            $blockId = is_string($block['id'] ?? null) ? $block['id'] : null;
            $pin = $blockId === null ? null : $pins->get($blockId);
            $pinnedVersion = $pin === null ? null : $pinnedVersions->get($pin->embedded_version_id);
            $pinnedSnapshot = $pinnedVersion?->snapshot;

            if ($pinnedSnapshot === null || $pinnedVersion === null) {
                $resolved[] = [
                    'type' => 'embed',
                    'snapshot_id' => $snapshotId,
                    'restricted' => true,
                ];

                continue;
            }

            // REQ-M5-005: even though the report owner could embed it,
            // re-check the policy for the current viewer in case future
            // policy changes diverge ownership and embed visibility.
            if (! $this->policy->view($request->user(), $pinnedSnapshot)) {
                $resolved[] = [
                    'type' => 'embed',
                    'snapshot_id' => (int) $pinnedSnapshot->id,
                    'restricted' => true,
                ];

                continue;
            }

            $currentRevision = $pinnedSnapshot->currentVersion?->revision ?? $pinnedVersion->revision;

            $resolved[] = [
                'type' => 'embed',
                'snapshot_id' => (int) $pinnedSnapshot->id,
                'snapshot_slug' => $pinnedSnapshot->slug,
                'workbench_slug' => $pinnedSnapshot->workbench?->slug,
                'title' => $pinnedSnapshot->title ?? $pinnedSnapshot->slug,
                'view_type' => $pinnedVersion->view_type,
                'pinned_version_id' => (int) $pinnedVersion->id,
                'pinned_revision' => (int) $pinnedVersion->revision,
                'current_revision' => (int) $currentRevision,
                'is_stale' => (int) $currentRevision !== (int) $pinnedVersion->revision,
                'data_payload' => $pinnedVersion->data_payload,
            ];
        }

        return $resolved;
    }

    /**
     * Resolve the active version id: an explicit `?revision=` wins, otherwise
     * fall back to `current_version_id`, then to the latest revision. The
     * caller fetches the full row once — this avoids the extra query the
     * previous pair of helpers ran for every owner request.
     *
     * @param  Collection<int, SnapshotVersion>  $versions
     */
    private function resolveActiveVersionId(Request $request, Snapshot $snapshot, $versions): int
    {
        $requestedRevision = $request->query('revision');

        if ($requestedRevision !== null && ctype_digit((string) $requestedRevision)) {
            $match = $versions->firstWhere('revision', (int) $requestedRevision);

            if (! $match instanceof SnapshotVersion) {
                abort(404);
            }

            return (int) $match->id;
        }

        return (int) ($snapshot->current_version_id ?? $versions->first()->id);
    }
}
