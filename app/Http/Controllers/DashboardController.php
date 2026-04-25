<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SnapshotVersion;
use App\Models\Workbench;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();

        $recentSnapshots = SnapshotVersion::query()
            ->with(['snapshot.workbench'])
            ->whereHas('snapshot.workbench', fn ($q) => $q->where('owner_user_id', $user->id))
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn (SnapshotVersion $v) => [
                'workbench_slug' => $v->snapshot->workbench->slug,
                'workbench_name' => $v->snapshot->workbench->name,
                'snapshot_slug' => $v->snapshot->slug,
                'snapshot_title' => $v->snapshot->title,
                'revision' => $v->revision,
                'view_type' => $v->view_type,
                'created_at' => $v->created_at?->toIso8601String(),
                'created_human' => $v->created_at?->diffForHumans(syntax: CarbonInterface::DIFF_ABSOLUTE, short: true),
            ])
            ->all();

        $lastVersionAtSub = DB::table('snapshot_versions')
            ->join('snapshots', 'snapshots.id', '=', 'snapshot_versions.snapshot_id')
            ->whereColumn('snapshots.workbench_id', 'workbenches.id')
            ->selectRaw('max(snapshot_versions.created_at)');

        $snapshotCountSub = DB::table('snapshots')
            ->whereColumn('snapshots.workbench_id', 'workbenches.id')
            ->selectRaw('count(*)');

        $latestSnapshotSlugSub = DB::table('snapshot_versions')
            ->join('snapshots', 'snapshots.id', '=', 'snapshot_versions.snapshot_id')
            ->whereColumn('snapshots.workbench_id', 'workbenches.id')
            ->orderByDesc('snapshot_versions.created_at')
            ->limit(1)
            ->select('snapshots.slug');

        $workbenches = Workbench::query()
            ->where('owner_user_id', $user->id)
            ->select('workbenches.*')
            ->selectSub($lastVersionAtSub, 'last_version_at')
            ->selectSub($snapshotCountSub, 'snapshot_count')
            ->selectSub($latestSnapshotSlugSub, 'latest_snapshot_slug')
            ->orderByRaw('('.$lastVersionAtSub->toSql().') desc nulls last')
            ->orderByDesc('workbenches.created_at')
            ->get()
            ->map(function (Workbench $w): array {
                $lastVersionAt = $w->getAttribute('last_version_at');
                $lastActivity = $lastVersionAt !== null
                    ? Carbon::parse($lastVersionAt)
                    : $w->created_at;

                return [
                    'slug' => $w->slug,
                    'name' => $w->name,
                    'last_activity_at' => $lastActivity?->toIso8601String(),
                    'last_activity_human' => $lastActivity?->diffForHumans(
                        syntax: CarbonInterface::DIFF_ABSOLUTE,
                        short: true,
                    ),
                    'snapshot_count' => (int) $w->getAttribute('snapshot_count'),
                    'latest_snapshot_slug' => $w->getAttribute('latest_snapshot_slug'),
                ];
            })
            ->all();

        return Inertia::render('dashboard', [
            'recentSnapshots' => $recentSnapshots,
            'workbenches' => $workbenches,
        ]);
    }
}
