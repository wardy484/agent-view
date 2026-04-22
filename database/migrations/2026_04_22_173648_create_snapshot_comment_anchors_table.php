<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AGENT-RENDER-6: optional inline-position data for a comment.
 *
 * A comment can be anchored to a specific region of the rendered view — e.g.
 * a table cell (`anchor_type=cell`), a DOM node (`element_id`), or a text
 * range within a slide. `anchor_data` is an opaque JSONB blob whose shape is
 * owned by the view renderer; storing it separately keeps the comments table
 * cleanly queryable for thread-level operations.
 *
 * Most comments will have zero anchors (general thread comment). One anchor
 * per comment is the common case; the schema allows N to leave room for
 * multi-select pins without a schema change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('snapshot_comment_anchors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('comment_id')
                ->constrained('snapshot_version_comments')
                ->cascadeOnDelete();
            $table->string('anchor_type');
            $table->jsonb('anchor_data');
            $table->timestamp('created_at')->useCurrent();

            $table->index('comment_id');
            $table->index('anchor_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('snapshot_comment_anchors');
    }
};
