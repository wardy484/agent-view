<?php

declare(strict_types=1);

use App\Nexus\Comments\CommentsRevisionTracker;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * REQ-M6-016: monotonic counter on the `snapshots` row, bumped by
 * {@see CommentsRevisionTracker} on every comment, reply,
 * reaction, or resolution write. The frontend uses it as a cheap
 * change-detection cursor for the 8-second sidebar polling loop — when the
 * counter doesn't move, the controller short-circuits with `no_change: true`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('snapshots', function (Blueprint $table): void {
            $table->bigInteger('comments_revision')->unsigned()->default(0)->after('share_token');
        });

        DB::table('snapshots')->update(['comments_revision' => 0]);
    }

    public function down(): void
    {
        Schema::table('snapshots', function (Blueprint $table): void {
            $table->dropColumn('comments_revision');
        });
    }
};
