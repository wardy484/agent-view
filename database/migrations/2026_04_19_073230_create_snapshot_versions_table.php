<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('snapshot_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('snapshot_id')->constrained('snapshots')->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->string('view_type');
            $table->jsonb('data_payload');
            $table->jsonb('metadata')->nullable();
            $table->text('preview_html')->nullable();
            $table->timestamps();

            // REQ-M1-003: revisions are monotonic AND unique per snapshot.
            $table->unique(['snapshot_id', 'revision']);
        });

        // Wire snapshots.current_version_id now that the target table exists.
        Schema::table('snapshots', function (Blueprint $table): void {
            $table->foreign('current_version_id')
                ->references('id')
                ->on('snapshot_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('snapshots', function (Blueprint $table): void {
            $table->dropForeign(['current_version_id']);
        });

        Schema::dropIfExists('snapshot_versions');
    }
};
