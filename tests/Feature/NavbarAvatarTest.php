<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the topbar avatar button compactly with a gap and no oversized circle', function () {
    // Obsolete: this test asserted on the deleted `nexus-shell-layout.tsx`'s
    // topbar avatar styling. The app shell is now the shadcn `<Sidebar>` and
    // the user avatar is rendered by `NavUser` in the sidebar footer, not in
    // a topbar. The styling concerns this test guarded (compact size, neutral
    // bg) belong to `NavUser` / `UserInfo` and need re-expressing against
    // those components if still desired.
})->skip('Pending re-expression against NavUser/UserInfo after sidebar rewrite.');
