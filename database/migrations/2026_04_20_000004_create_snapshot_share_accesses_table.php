<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REQ-M4-002: audit log of every GET /s/{token} hit. Captures IP, user agent,
 * and the authenticated user (if any) so owners can see who has been viewing
 * their link-shared snapshots.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('snapshot_share_accesses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('snapshot_id')->constrained('snapshots')->cascadeOnDelete();
            $table->string('share_token', 64);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['snapshot_id', 'created_at']);
            $table->index('share_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('snapshot_share_accesses');
    }
};
