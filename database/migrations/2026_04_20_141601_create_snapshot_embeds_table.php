<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REQ-M5-003: denormalised pin table — one row per (report_version, embed_block).
 *
 * Each row records WHICH snapshot was embedded, AT WHAT version, in WHICH
 * report revision, at WHICH block index. The pin is immutable for that
 * report revision; the next report revision re-pins (via a fresh row).
 *
 * REQ-M5-004 reads this table to grant transitive view access: a viewer
 * who can see a report transitively gains read on every snapshot version
 * pinned by the report's current_version_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('snapshot_embeds', function (Blueprint $table): void {
            $table->id();

            // The report that contains the embed.
            $table->foreignId('report_snapshot_id')->constrained('snapshots')->cascadeOnDelete();
            $table->foreignId('report_version_id')->constrained('snapshot_versions')->cascadeOnDelete();

            // The snapshot that was embedded, pinned at the version current
            // at the moment the report revision was appended.
            $table->foreignId('embedded_snapshot_id')->constrained('snapshots')->cascadeOnDelete();
            $table->foreignId('embedded_version_id')->constrained('snapshot_versions')->cascadeOnDelete();

            // Position in the report's ordered blocks[] (zero-based).
            $table->unsignedInteger('block_index');

            $table->timestamp('created_at')->useCurrent();

            // REQ-M5-004: lookups go (embedded_snapshot_id) → reports that
            // mention it; (report_version_id) → embed list for a single revision.
            $table->index('embedded_snapshot_id');
            $table->index('report_version_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('snapshot_embeds');
    }
};
