<?php

use App\Http\Controllers\AgentActivityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\PublicSnapshotController;
use App\Http\Controllers\SnapshotController;
use App\Http\Controllers\WorkbenchOrganisationController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

// Marketing & company pages
Route::inertia('contact', 'contact')->name('contact');

// Legal pages
Route::inertia('terms', 'legal/terms')->name('legal.terms');
Route::inertia('privacy', 'legal/privacy')->name('legal.privacy');
Route::inertia('cookies', 'legal/cookies')->name('legal.cookies');
Route::inertia('acceptable-use', 'legal/acceptable-use')->name('legal.acceptable-use');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'show'])->name('dashboard');
});

// Workbench snapshot page — renders the snapshot via the appropriate view
// component (REQ-M1-005 wires the Table view; later milestones add Kanban,
// Flowchart, SlideDeck). The page resolves data_payload from the snapshot's
// current_version_id (REQ-M1-010) and the React component iterates rows.
Route::get('/workbenches/{workbench:slug}/snapshots/{snapshot:slug}', [SnapshotController::class, 'show'])
    ->name('workbench.snapshot.show');

// REQ-M3-004: "Send back to Agent" creates a follow_up_contexts row.
Route::middleware(['web', 'auth'])
    ->post('/workbenches/{workbench:slug}/follow-ups', [FollowUpController::class, 'store'])
    ->name('workbench.follow-ups.store');

// REQ-M3-007: singleton "Agent Activity" dashboard — rendered by snapshot.tsx.
Route::get('/workbenches/{workbench:slug}/agent-activity', [AgentActivityController::class, 'show'])
    ->name('workbench.agent-activity');

// REQ-M6-001..005: owner-only organisational mutations on a workbench.
// All routes sit under an authenticated prefix, are route-model-bound by
// slug so unknown slugs 404 automatically, and defer to WorkbenchPolicy
// for the 403 non-owner response.
Route::middleware(['web', 'auth'])
    ->prefix('workbenches/{workbench:slug}')
    ->controller(WorkbenchOrganisationController::class)
    ->group(function (): void {
        Route::patch('/', 'rename')->name('workbench.rename');
        Route::post('/pin', 'pin')->name('workbench.pin');
        Route::delete('/pin', 'unpin')->name('workbench.unpin');
    });

// REQ-M4-002: public read-only link to a snapshot with visibility=link.
// Throttled per-IP to resist token brute-forcing; every successful hit is
// audited in snapshot_share_accesses.
Route::get('/s/{token}', [PublicSnapshotController::class, 'show'])
    ->middleware('throttle:share-link')
    ->where('token', '[A-Za-z0-9_-]+')
    ->name('snapshot.public');

require __DIR__.'/settings.php';
