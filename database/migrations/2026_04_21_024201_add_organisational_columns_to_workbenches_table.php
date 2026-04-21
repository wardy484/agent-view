<?php

declare(strict_types=1);

use App\Nexus\WorkbenchActivityBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REQ-M6-000: workbenches gain four owner-scoped organisational columns,
 * all nullable timestamps — `pinned_at`, `archived_at`, `deleted_at`
 * (Eloquent SoftDeletes) and `last_activity_at`. The column is back-filled
 * from the most recent `snapshot_versions.created_at` per workbench, falling
 * back to `workbenches.created_at` when the workbench has no versions yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workbenches', function (Blueprint $table): void {
            $table->timestamp('pinned_at')->nullable()->after('owner_user_id');
            $table->timestamp('archived_at')->nullable()->after('pinned_at');
            $table->timestamp('last_activity_at')->nullable()->after('archived_at');
            $table->softDeletes();

            $table->index(['owner_user_id', 'pinned_at']);
            $table->index(['owner_user_id', 'archived_at']);
        });

        // Postgres supports ordered composite indexes; the dashboard default
        // sort relies on `last_activity_at desc` so we create it explicitly
        // rather than via Blueprint::index().
        Schema::getConnection()->statement(
            'create index workbenches_owner_activity_desc_idx
             on workbenches (owner_user_id, last_activity_at desc)'
        );

        WorkbenchActivityBackfill::run();
    }

    public function down(): void
    {
        Schema::getConnection()->statement(
            'drop index if exists workbenches_owner_activity_desc_idx'
        );

        Schema::table('workbenches', function (Blueprint $table): void {
            $table->dropIndex(['owner_user_id', 'pinned_at']);
            $table->dropIndex(['owner_user_id', 'archived_at']);
            $table->dropSoftDeletes();
            $table->dropColumn(['pinned_at', 'archived_at', 'last_activity_at']);
        });
    }
};
