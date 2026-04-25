<?php

namespace App\Http\Middleware;

use App\Nexus\SidebarSharingData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(private readonly SidebarSharingData $sharingData) {}

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
        $user = $request->user();
        $emptySharing = ['shared_with_me' => [], 'owned_badges' => []];

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // REQ-M4-007: sidebar data — "Shared with me" entries for the
            // signed-in user and share-count / link badges for every snapshot
            // they own. Guests receive empty arrays. Deferred so the initial
            // page shell ships without waiting on this query.
            'sharing' => Inertia::defer(fn () => $user ? $this->sharingData->for($user) : $emptySharing),
        ];
    }
}
