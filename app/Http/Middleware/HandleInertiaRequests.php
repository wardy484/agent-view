<?php

namespace App\Http\Middleware;

use App\Models\Workbench;
use App\Nexus\SidebarNavData;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(private readonly SidebarNavData $navData) {}

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'workbenches' => fn () => $request->user()
                ? $this->workbenchNav()
                : [],
            // REQ-M6-008: sidebar nav data — pinned + recent workbenches for
            // the signed-in owner (single eager-loaded query), plus
            // REQ-M4-007 shared_with_me and owned_badges. Guests receive
            // empty arrays.
            'nav' => $this->navData->for($request->user()),
        ];
    }

    /**
     * @return list<array{slug: string, name: string, snapshot_count: int, latest_snapshot_slug: ?string}>
     */
    private function workbenchNav(): array
    {
        return Workbench::query()
            ->withCount('snapshots')
            ->with(['snapshots' => fn ($q) => $q->latest('updated_at')->limit(1)])
            ->orderBy('name')
            ->get()
            ->map(fn (Workbench $w) => [
                'slug' => $w->slug,
                'name' => $w->name,
                'snapshot_count' => (int) $w->snapshots_count,
                'latest_snapshot_slug' => $w->snapshots->first()?->slug,
            ])
            ->all();
    }
}
