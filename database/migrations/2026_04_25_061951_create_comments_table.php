<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * REQ-M6-003: `comments` is the canonical store for inline review comments
 * on report markdown blocks. Each row is either a root comment (anchored to
 * a block via the `anchor_*` quote columns) or a reply (no anchor; chained
 * to its parent via `parent_comment_id`). Replies cannot have replies; a
 * trigger enforces depth = 1 at the database level.
 *
 * Suggestions (`kind = 'suggestion'`) carry `proposed_text` describing the
 * desired replacement for `anchor_quote`; plain comments must leave it null.
 *
 * Comments soft-delete via `deleted_at` so the audit trail is preserved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table): void {
            $table->id();

            // Snapshot the comment lives on; cascade so dropping a snapshot
            // tears down its review history.
            $table->foreignId('snapshot_id')->constrained('snapshots')->cascadeOnDelete();

            // Block within the report payload the root comment anchors to.
            // Replies inherit their parent's block but still set this column
            // for cheap per-block lookups.
            $table->uuid('block_id');

            // Self-FK for thread replies. Cascade so deleting a root comment
            // also drops its replies.
            $table->foreignId('parent_comment_id')->nullable()->constrained('comments')->cascadeOnDelete();

            $table->string('kind', 16)->default('comment');
            $table->text('body');
            $table->text('proposed_text')->nullable();

            // Anchor columns — present on root comments, null on replies.
            $table->text('anchor_quote')->nullable();
            $table->text('anchor_prefix')->nullable();
            $table->text('anchor_suffix')->nullable();
            $table->unsignedInteger('anchor_start_hint')->nullable();
            $table->unsignedInteger('anchor_end_hint')->nullable();

            $table->string('status', 16)->default('open');
            $table->string('resolution', 16)->nullable();

            // Versions: created_on_version_id is immutable for audit;
            // addressed_on_version_id is set when the thread is resolved
            // and survives that revision being deleted.
            $table->foreignId('created_on_version_id')->constrained('snapshot_versions')->cascadeOnDelete();
            $table->foreignId('addressed_on_version_id')->nullable()->constrained('snapshot_versions')->nullOnDelete();

            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_kind', 16)->default('user');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['snapshot_id', 'deleted_at']);
            $table->index('block_id');
            $table->index('parent_comment_id');
            $table->index('status');
        });

        // REQ-M6-003: kind ∈ {comment, suggestion}.
        DB::statement(
            'ALTER TABLE comments ADD CONSTRAINT comments_kind_check '
            ."CHECK (kind IN ('comment', 'suggestion'))"
        );

        // REQ-M6-003: status ∈ {open, resolved, stale, wontfix}.
        DB::statement(
            'ALTER TABLE comments ADD CONSTRAINT comments_status_check '
            ."CHECK (status IN ('open', 'resolved', 'stale', 'wontfix'))"
        );

        // REQ-M6-003: resolution ∈ {user, agent} when set.
        DB::statement(
            'ALTER TABLE comments ADD CONSTRAINT comments_resolution_check '
            ."CHECK (resolution IS NULL OR resolution IN ('user', 'agent'))"
        );

        // REQ-M6-003: author_kind ∈ {user, agent}.
        DB::statement(
            'ALTER TABLE comments ADD CONSTRAINT comments_author_kind_check '
            ."CHECK (author_kind IN ('user', 'agent'))"
        );

        // REQ-M6-003: replies (parent_comment_id IS NOT NULL) carry no anchor
        // and are always plain comments.
        DB::statement(
            'ALTER TABLE comments ADD CONSTRAINT comments_reply_shape_check '
            .'CHECK (parent_comment_id IS NULL OR ('
            .'anchor_quote IS NULL '
            .'AND anchor_prefix IS NULL '
            .'AND anchor_suffix IS NULL '
            .'AND anchor_start_hint IS NULL '
            .'AND anchor_end_hint IS NULL '
            ."AND kind = 'comment'"
            .'))'
        );

        // REQ-M6-003: kind=suggestion <=> proposed_text IS NOT NULL.
        DB::statement(
            'ALTER TABLE comments ADD CONSTRAINT comments_suggestion_shape_check '
            .'CHECK ('
            ."(kind = 'suggestion' AND proposed_text IS NOT NULL) "
            ."OR (kind = 'comment' AND proposed_text IS NULL)"
            .')'
        );

        // REQ-M6-003: depth = 1 — replies cannot themselves have replies.
        // Enforced via a BEFORE INSERT/UPDATE trigger that walks one level up.
        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION comments_no_nested_replies()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.parent_comment_id IS NOT NULL THEN
        IF EXISTS (
            SELECT 1 FROM comments
            WHERE id = NEW.parent_comment_id
              AND parent_comment_id IS NOT NULL
        ) THEN
            RAISE EXCEPTION 'comments.parent_comment_id must reference a root comment (depth = 1)';
        END IF;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
SQL);

        DB::statement(
            'CREATE TRIGGER comments_no_nested_replies_trigger '
            .'BEFORE INSERT OR UPDATE ON comments '
            .'FOR EACH ROW EXECUTE FUNCTION comments_no_nested_replies()'
        );
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS comments_no_nested_replies_trigger ON comments');
        DB::statement('DROP FUNCTION IF EXISTS comments_no_nested_replies()');
        Schema::dropIfExists('comments');
    }
};
