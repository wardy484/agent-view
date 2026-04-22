<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\McpCallLog;
use App\Models\Snapshot;
use App\Models\SnapshotVersion;
use App\Models\Workbench;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function show(Request $request): Response
    {
        $startOfDay = now()->startOfDay();

        $workbenchCount = Workbench::count();
        $snapshotCount = Snapshot::count();
        $revisionsToday = SnapshotVersion::where('created_at', '>=', $startOfDay)->count();
        $mcpCallsToday = McpCallLog::where('created_at', '>=', $startOfDay)->count();

        $ownedWorkbenches = $this->ownedWorkbenches($request);

        $recentVersions = SnapshotVersion::query()
            ->with(['snapshot.workbench'])
            ->orderByDesc('created_at')
            ->limit(6)
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

        $viewTypeSamples = $this->latestPerViewType();

        return Inertia::render('dashboard', [
            'kpis' => [
                'workbenches' => $workbenchCount,
                'snapshots' => $snapshotCount,
                'revisions_today' => $revisionsToday,
                'mcp_calls_today' => $mcpCallsToday,
            ],
            'recentSnapshots' => $recentVersions,
            'viewTypeSamples' => $viewTypeSamples,
            'ownedWorkbenches' => $ownedWorkbenches,
        ]);
    }

    /**
     * REQ-M6-007: workbenches owned by the current user, including archived
     * and soft-deleted rows, sorted by `last_activity_at desc`. The client
     * splits this list into Active / Archived / Trash tabs.
     *
     * @return list<array{
     *     slug: string,
     *     name: string,
     *     snapshot_count: int,
     *     pinned_at: ?string,
     *     archived_at: ?string,
     *     deleted_at: ?string,
     *     last_activity_at: ?string,
     *     last_activity_human: ?string,
     *     latest_snapshot_slug: ?string,
     * }>
     */
    private function ownedWorkbenches(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return [];
        }

        return Workbench::withTrashed()
            ->where('owner_user_id', $user->id)
            ->withCount('snapshots')
            ->with(['snapshots' => fn ($q) => $q->latest('updated_at')->limit(1)])
            ->orderByRaw('last_activity_at DESC NULLS LAST')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Workbench $w) => [
                'slug' => $w->slug,
                'name' => $w->name,
                'snapshot_count' => (int) $w->snapshots_count,
                'pinned_at' => $w->pinned_at?->toIso8601String(),
                'archived_at' => $w->archived_at?->toIso8601String(),
                'deleted_at' => $w->deleted_at?->toIso8601String(),
                'last_activity_at' => $w->last_activity_at?->toIso8601String(),
                'last_activity_human' => $w->last_activity_at?->diffForHumans(syntax: CarbonInterface::DIFF_ABSOLUTE, short: true),
                'latest_snapshot_slug' => $w->snapshots->first()?->slug,
            ])
            ->all();
    }

    /**
     * Latest snapshot per view_type. Powers the dashboard view-type cards so
     * each card links to a real example when one exists.
     *
     * @return array<string, array{workbench_slug: string, snapshot_slug: string, snapshot_title: string|null}|null>
     */
    private function latestPerViewType(): array
    {
        // REQ-M5-000: `report` is a narrative view_type that bundles other
        // snapshots inline; the dashboard surfaces it alongside the four
        // structured zones so users can find a sample report the same way
        // they find a table or slide deck.
        $viewTypes = ['slide_deck', 'table', 'kanban', 'flowchart', 'report'];
        $samples = array_fill_keys($viewTypes, null);

        $latest = SnapshotVersion::query()
            ->with(['snapshot.workbench'])
            ->whereIn('view_type', $viewTypes)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('view_type');

        foreach ($viewTypes as $viewType) {
            $version = $latest->get($viewType)?->first();

            if ($version === null) {
                continue;
            }

            $samples[$viewType] = [
                'workbench_slug' => $version->snapshot->workbench->slug,
                'snapshot_slug' => $version->snapshot->slug,
                'snapshot_title' => $version->snapshot->title,
            ];
        }

        return $samples;
    }
}
