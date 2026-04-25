<?php

declare(strict_types=1);

use App\Nexus\Comments\CommentReactionService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * REQ-M6-007: `comment_reactions` records the fixed-emoji "social" reactions
 * users can place on comments — root or reply alike. The set of permitted
 * emojis is enforced by a CHECK constraint so application code can rely on
 * the column without a separate validation pass. Reactions are toggleable
 * via {@see CommentReactionService::toggle()}: the unique
 * `(comment_id, user_id, emoji)` index makes "second click removes" the
 * natural shape — no upsert dance needed.
 *
 * Only `created_at` is tracked: a reaction is either present or absent, so
 * there's nothing to update. Cascade deletes on both FKs keep us tidy when
 * comments or users disappear.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comment_reactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('comment_id')->constrained('comments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('emoji', 8);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['comment_id', 'user_id', 'emoji'], 'comment_reactions_unique');
            $table->index('comment_id');
        });

        // REQ-M6-007: emoji ∈ the fixed 6-emoji review set.
        DB::statement(
            'ALTER TABLE comment_reactions ADD CONSTRAINT comment_reactions_emoji_check '
            ."CHECK (emoji IN ('👍', '👎', '❤️', '🎉', '🤔', '👀'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('comment_reactions');
    }
};
