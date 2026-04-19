<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

// Workbench snapshot page. The real Inertia page lands in REQ-M1-014; for
// now we just need a named, addressable URL that the present_structured_data
// MCP tool can hand back to its caller (REQ-M1-004).
Route::get('/workbenches/{workbench:slug}/snapshots/{snapshot:slug}', function () {
    return response('Workbench snapshot page (REQ-M1-014)', 200);
})->name('workbench.snapshot.show');

require __DIR__.'/settings.php';
