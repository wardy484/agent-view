<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * REQ-M6-002: replace `snapshot_embeds.block_index` with `block_id`.
 *
 * Embed pins now reference report blocks by stable UUID (REQ-M6-001) rather
 * than positional index. The migration:
 *
 *  1. Adds `block_id uuid NULL` (indexed).
 *  2. Walks every existing pin, locates its block in the parent report
 *     version's payload by `block_index`, ensures the block carries an
 *     `id` (assigning a fresh UUID if missing — and writing the mutated
 *     `data_payload` back in place via a raw query). Writing through
 *     SnapshotVersioning::append() would create a brand-new revision; we
 *     are amending the *existing* revision in-place during back-fill,
 *     which is the only legitimate exception to the "SnapshotVersioning
 *     is the sole writer" rule (per AGENTS.md, this is a one-shot data
 *     migration, not a runtime mutation path).
 *  3. Sets `block_id` to NOT NULL.
 *  4. Drops `block_index`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('snapshot_embeds', function (Blueprint $table): void {
            $table->uuid('block_id')->nullable()->after('embedded_version_id');
            $table->index('block_id');
        });

        DB::transaction(function (): void {
            $rows = DB::table('snapshot_embeds')->orderBy('id')->get();

            foreach ($rows as $row) {
                $version = DB::table('snapshot_versions')
                    ->where('id', $row->report_version_id)
                    ->first(['id', 'data_payload']);

                if ($version === null) {
                    continue;
                }

                $payload = is_string($version->data_payload)
                    ? json_decode($version->data_payload, true)
                    : (array) $version->data_payload;

                if (! is_array($payload)) {
                    continue;
                }

                $blocks = $payload['blocks'] ?? [];

                if (! is_array($blocks)) {
                    continue;
                }

                $blocks = array_values($blocks);
                $index = (int) $row->block_index;

                if (! isset($blocks[$index]) || ! is_array($blocks[$index])) {
                    continue;
                }

                $block = $blocks[$index];
                $existingId = $block['id'] ?? null;

                if (! is_string($existingId) || ! Str::isUuid($existingId)) {
                    $existingId = (string) Str::uuid();
                    $block['id'] = $existingId;
                    $blocks[$index] = $block;
                    $payload['blocks'] = $blocks;

                    DB::table('snapshot_versions')
                        ->where('id', $version->id)
                        ->update(['data_payload' => json_encode($payload)]);
                }

                DB::table('snapshot_embeds')
                    ->where('id', $row->id)
                    ->update(['block_id' => $existingId]);
            }
        });

        Schema::table('snapshot_embeds', function (Blueprint $table): void {
            $table->uuid('block_id')->nullable(false)->change();
        });

        Schema::table('snapshot_embeds', function (Blueprint $table): void {
            $table->dropColumn('block_index');
        });
    }

    public function down(): void
    {
        Schema::table('snapshot_embeds', function (Blueprint $table): void {
            $table->unsignedInteger('block_index')->default(0);
        });

        Schema::table('snapshot_embeds', function (Blueprint $table): void {
            $table->dropIndex(['block_id']);
            $table->dropColumn('block_id');
        });
    }
};
