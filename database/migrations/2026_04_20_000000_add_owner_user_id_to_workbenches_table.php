<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REQ-M4-000: workbenches gain a nullable `owner_user_id`. Sharing REQs
 * (REQ-M4-005) refuse to share any workbench whose owner is null. The
 * paired data migration below {@see backfill()} assigns an owner from
 * the earliest non-null `mcp_call_logs.user_id` for each workbench.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workbenches', function (Blueprint $table): void {
            $table->foreignId('owner_user_id')
                ->nullable()
                ->after('name')
                ->constrained('users')
                ->nullOnDelete();

            $table->index('owner_user_id');
        });

        // REQ-M4-000 back-fill: assign historical ownership from mcp_call_logs.
        \App\Nexus\WorkbenchOwnerBackfill::run();
    }

    public function down(): void
    {
        Schema::table('workbenches', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('owner_user_id');
        });
    }
};
