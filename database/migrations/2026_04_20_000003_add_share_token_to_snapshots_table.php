<?php

declare(strict_types=1);

use App\Models\Snapshot;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REQ-M4-002: snapshots carry an unguessable `share_token` that is only
 * populated when `visibility = link`. {@see Snapshot::setVisibility()}
 * rotates the token on Private → Link transitions and clears it on every
 * other transition.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('snapshots', function (Blueprint $table): void {
            $table->string('share_token', 64)->nullable()->unique()->after('visibility');
        });
    }

    public function down(): void
    {
        Schema::table('snapshots', function (Blueprint $table): void {
            $table->dropUnique(['share_token']);
            $table->dropColumn('share_token');
        });
    }
};
