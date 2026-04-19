<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follow_up_contexts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workbench_id')->constrained('workbenches')->cascadeOnDelete();
            $table->foreignId('snapshot_id')->nullable()->constrained('snapshots')->cascadeOnDelete();
            $table->jsonb('payload');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            // REQ-M3-003/REQ-M3-005: the hot query is "all unconsumed rows for a
            // workbench" — composite index covers it.
            $table->index(['workbench_id', 'consumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_up_contexts');
    }
};
