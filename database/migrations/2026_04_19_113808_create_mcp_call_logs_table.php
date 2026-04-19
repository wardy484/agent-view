<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_call_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('tool_name');
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('status')->default('ok');
            $table->unsignedInteger('payload_bytes')->default(0);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('workbench_id')->nullable()->constrained('workbenches')->nullOnDelete();
            $table->timestamps();

            $table->index(['tool_name']);
            $table->index(['workbench_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_call_logs');
    }
};
