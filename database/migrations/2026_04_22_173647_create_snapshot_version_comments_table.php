<?php

declare(strict_types=1);

use App\Models\SnapshotVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AGENT-RENDER-6: version-scoped comment threads.
 *
 * Comments anchor to a single {@see SnapshotVersion} so discussion
 * stays pinned to the revision it refers to — the same conversation does not
 * follow the snapshot forward as new revisions are appended.
 *
 * Delete strategy: soft-delete. A parent comment may have replies, and hard-
 * deleting would either orphan the replies or require cascading them away;
 * both destroy thread context for reviewers. `deleted_at` lets the UI render
 * a tombstone ("[deleted]") while preserving reply structure.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('snapshot_version_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('snapshot_version_id')
                ->constrained('snapshot_versions')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('snapshot_version_comments')
                ->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Thread listing: fetch all comments on a version in chronological order.
            $table->index(['snapshot_version_id', 'created_at']);
            // Reply loading: fetch all children of a specific parent.
            $table->index('parent_id');
            // Filter by resolution state within a version.
            $table->index(['snapshot_version_id', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('snapshot_version_comments');
    }
};
