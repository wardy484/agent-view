<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CommentStatus;
use App\Models\Snapshot;
use App\Models\Workbench;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class WorkbenchController extends Controller
{
    public function show(Request $request, Workbench $workbench): Response
    {
        if ((int) $workbench->owner_user_id !== (int) $request->user()->id) {
            abort(403);
        }

        $latestRevisionSub = DB::table('snapshot_versions')
            ->whereColumn('snapshot_versions.id', 'snapshots.current_version_id')
            ->select('snapshot_versions.revision')
            ->limit(1);

        $latestViewTypeSub = DB::table('snapshot_versions')
            ->whereColumn('snapshot_versions.id', 'snapshots.current_version_id')
            ->select('snapshot_versions.view_type')
            ->limit(1);

        $lastActivityAtSub = DB::table('snapshot_versions')
            ->whereColumn('snapshot_versions.id', 'snapshots.current_version_id')
            ->select('snapshot_versions.created_at')
            ->limit(1);

        $activeShareCountSub = DB::table('snapshot_shares')
            ->whereColumn('snapshot_shares.snapshot_id', 'snapshots.id')
            ->whereNull('snapshot_shares.revoked_at')
            ->selectRaw('count(*)');

        $openCommentCountSub = DB::table('comments')
            ->whereColumn('comments.snapshot_id', 'snapshots.id')
            ->whereNull('comments.deleted_at')
            ->whereNull('comments.parent_comment_id')
            ->where('comments.status', CommentStatus::Open->value)
            ->selectRaw('count(*)');

        $views = Snapshot::query()
            ->where('workbench_id', $workbench->id)
            ->select('snapshots.*')
            ->selectSub($latestRevisionSub, 'current_revision')
            ->selectSub($latestViewTypeSub, 'current_view_type')
            ->selectSub($lastActivityAtSub, 'last_activity_at')
            ->selectSub($activeShareCountSub, 'active_share_count')
            ->selectSub($openCommentCountSub, 'open_comment_count')
            ->orderByRaw('('.$lastActivityAtSub->toSql().') desc nulls last')
            ->orderByDesc('snapshots.created_at')
            ->get()
            ->map(function (Snapshot $snapshot): array {
                $lastActivityAt = $snapshot->getAttribute('last_activity_at');
                $lastActivity = $lastActivityAt !== null
                    ? Carbon::parse($lastActivityAt)
                    : $snapshot->created_at;

                return [
                    'slug' => $snapshot->slug,
                    'title' => $snapshot->title,
                    'current_revision' => $snapshot->getAttribute('current_revision') !== null
                        ? (int) $snapshot->getAttribute('current_revision')
                        : null,
                    'view_type' => $snapshot->getAttribute('current_view_type'),
                    'last_activity_at' => $lastActivity?->toIso8601String(),
                    'last_activity_human' => $lastActivity?->diffForHumans(
                        syntax: CarbonInterface::DIFF_ABSOLUTE,
                        short: true,
                    ),
                    'visibility' => $snapshot->visibility?->value,
                    'active_share_count' => (int) $snapshot->getAttribute('active_share_count'),
                    'has_link_share' => $snapshot->share_token !== null,
                    'open_comment_count' => (int) $snapshot->getAttribute('open_comment_count'),
                ];
            })
            ->values()
            ->all();

        return Inertia::render('workbench', [
            'workbench' => [
                'slug' => $workbench->slug,
                'name' => $workbench->name,
            ],
            'views' => $views,
        ]);
    }
}
