<?php

declare(strict_types=1);

use App\Nexus\Comments\BackfillBlockIds;
use Illuminate\Database\Migrations\Migration;

/**
 * REQ-M6-025: data-only back-fill. Amends existing snapshot_versions rows in
 * place — bypasses SnapshotVersioning::append() because we are not creating a
 * new revision; we are repairing the historical row's payload to support the
 * comments feature shipped post-creation.
 *
 * Snapshots created before REQ-M6-001 shipped have `data_payload.blocks`
 * arrays whose blocks lack the `id` field. The frontend's
 * `data-comment-block-id` attribute is then omitted (React strips undefined
 * props), `useMarkdownSelection`'s `findBlockAncestor` returns null, and the
 * floating selection pill never appears. This walks every report
 * `snapshot_versions` row and assigns a UUID v4 to every block missing an
 * id; rows whose blocks all already carry ids are skipped.
 *
 * Mirrors the pattern documented in
 * `2026_04_25_061415_add_block_id_to_snapshot_embeds_table.php` —
 * the only legitimate exception to the "SnapshotVersioning is the sole
 * writer" rule (one-shot data migration, not a runtime mutation path).
 *
 * Idempotent. `down()` is a no-op because comments now reference the
 * back-filled UUIDs.
 */
return new class extends Migration
{
    public function up(): void
    {
        BackfillBlockIds::run();
    }

    public function down(): void
    {
        // No-op: removing back-filled UUIDs would orphan comment anchors.
    }
};
