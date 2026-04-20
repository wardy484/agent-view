<?php

declare(strict_types=1);

use App\Models\SnapshotShare;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * REQ-M4-003: `snapshot_shares` is the explicit allowlist for `visibility=shared`
 * snapshots. Shares are never hard-deleted — {@see SnapshotShare::revoke()}
 * sets `revoked_at` instead. The unique index is partial (Postgres-only) so a
 * revoked row does not block re-sharing the same email later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('snapshot_shares', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('snapshot_id')->constrained('snapshots')->cascadeOnDelete();
            $table->string('email');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('granted_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('revoked_at')->nullable();

            $table->index('email');
            $table->index(['snapshot_id', 'revoked_at']);
            $table->index('user_id');
        });

        // REQ-M4-003: partial unique — only unrevoked shares are constrained so
        // a revoke+re-share cycle doesn't collide with historical rows.
        DB::statement(
            'CREATE UNIQUE INDEX snapshot_shares_snapshot_email_active_unique '
            .'ON snapshot_shares (snapshot_id, email) WHERE revoked_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS snapshot_shares_snapshot_email_active_unique');
        Schema::dropIfExists('snapshot_shares');
    }
};
