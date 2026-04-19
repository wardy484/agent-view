<?php

use App\Http\Controllers\SnapshotController;
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
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

// Workbench snapshot page — renders the snapshot via the appropriate view
// component (REQ-M1-005 wires the Table view; later milestones add Kanban,
// Flowchart, SlideDeck). The page resolves data_payload from the snapshot's
// current_version_id (REQ-M1-010) and the React component iterates rows.
Route::get('/workbenches/{workbench:slug}/snapshots/{snapshot:slug}', [SnapshotController::class, 'show'])
    ->name('workbench.snapshot.show');

require __DIR__.'/settings.php';
