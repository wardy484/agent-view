<?php

use App\Http\Controllers\AgentActivityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\PublicSnapshotController;
use App\Http\Controllers\SnapshotController;
use App\Http\Controllers\Snapshots\CommentAcceptanceController;
use App\Http\Controllers\Snapshots\CommentController;
use App\Http\Controllers\SnapshotShareController;
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

// REQ-M4-002: public read-only link to a snapshot with visibility=link.
// Throttled per-IP to resist token brute-forcing; every successful hit is
// audited in snapshot_share_accesses.
Route::get('/s/{token}', [PublicSnapshotController::class, 'show'])
    ->middleware('throttle:share-link')
    ->where('token', '[A-Za-z0-9_-]+')
    ->name('snapshot.public');

// REQ-M4-010: owner-only mutation endpoints backing the Share dialog. Every
// action re-asserts ownership in the controller (see authorizeOwner) — the
// `auth` middleware only proves there's a session, not who owns the workbench.
Route::middleware(['web', 'auth'])
    ->prefix('/workbenches/{workbench:slug}/snapshots/{snapshot:slug}')
    ->name('workbench.snapshot.')
    ->group(function (): void {
        Route::patch('/visibility', [SnapshotShareController::class, 'updateVisibility'])->name('visibility.update');
        Route::post('/share-token/rotate', [SnapshotShareController::class, 'rotateToken'])->name('share-token.rotate');
        Route::post('/shares', [SnapshotShareController::class, 'storeShare'])->name('shares.store');
        Route::delete('/shares/{share}', [SnapshotShareController::class, 'destroyShare'])->name('shares.destroy');
    });

// REQ-M6-008: owner-only acceptance of a suggestion comment. Resolves the
// anchor against the snapshot's current revision, patches the body with
// `proposed_text`, mints a new report revision, and atomically resolves the
// comment as user-applied. Stale anchors return HTTP 409.
Route::middleware(['web', 'auth'])
    ->post('/snapshots/{snapshot}/comments/{comment}/accept', CommentAcceptanceController::class)
    ->name('snapshots.comments.accept');

// REQ-M6-014: snapshot owner / share grantee creates a root review comment.
// Replies and resolutions ride on dedicated MCP tools (REQ-M6-010..011) or
// later M6 endpoints; this is the only path the React review UI uses to file
// new threads.
Route::middleware(['web', 'auth'])
    ->post('/snapshots/{snapshot}/comments', [CommentController::class, 'store'])
    ->name('snapshots.comments.store');

require __DIR__.'/settings.php';
