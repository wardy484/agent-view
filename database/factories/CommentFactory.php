<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CommentAuthorKind;
use App\Enums\CommentKind;
use App\Enums\CommentStatus;
use App\Models\Comment;
use App\Models\Snapshot;
use App\Models\SnapshotVersion;
use App\Models\User;
use App\Nexus\SnapshotVersioning;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'block_id' => Str::uuid()->toString(),
            'parent_comment_id' => null,
            'kind' => CommentKind::Comment->value,
            'body' => fake()->sentence(),
            'proposed_text' => null,
            'anchor_quote' => fake()->sentence(),
            'anchor_prefix' => fake()->words(3, true),
            'anchor_suffix' => fake()->words(3, true),
            'anchor_start_hint' => fake()->numberBetween(0, 100),
            'anchor_end_hint' => fake()->numberBetween(101, 200),
            'status' => CommentStatus::Open->value,
            'resolution' => null,
            'addressed_on_version_id' => null,
            'author_user_id' => User::factory(),
            'author_kind' => CommentAuthorKind::User->value,
        ];
    }

    public function configure(): self
    {
        return $this->afterMaking(function (Comment $comment): void {
            // Lazy-fill (snapshot_id, created_on_version_id) together so that
            // both columns target the same freshly-minted report revision.
            if (empty($comment->snapshot_id) || empty($comment->created_on_version_id)) {
                $resolved = $this->resolveSnapshotAndVersion($comment);
                if (empty($comment->snapshot_id)) {
                    $comment->snapshot_id = $resolved['snapshot']->id;
                }
                if (empty($comment->created_on_version_id)) {
                    $comment->created_on_version_id = $resolved['version']->id;
                }
            }
        });
    }

    public function reply(Comment $parent): self
    {
        return $this->state(fn () => [
            'snapshot_id' => $parent->snapshot_id,
            'block_id' => $parent->block_id,
            'parent_comment_id' => $parent->id,
            'kind' => CommentKind::Comment->value,
            'proposed_text' => null,
            'anchor_quote' => null,
            'anchor_prefix' => null,
            'anchor_suffix' => null,
            'anchor_start_hint' => null,
            'anchor_end_hint' => null,
            'created_on_version_id' => $parent->created_on_version_id,
        ]);
    }

    public function suggestion(string $proposedText): self
    {
        return $this->state(fn () => [
            'kind' => CommentKind::Suggestion->value,
            'proposed_text' => $proposedText,
        ]);
    }

    public function asAgent(): self
    {
        return $this->state(fn () => [
            'author_kind' => CommentAuthorKind::Agent->value,
        ]);
    }

    /**
     * If the caller supplied a snapshot_id, reuse its latest revision rather
     * than minting another. Otherwise create a fresh snapshot + report v1.
     *
     * @return array{snapshot: Snapshot, version: SnapshotVersion}
     */
    private function resolveSnapshotAndVersion(Comment $comment): array
    {
        if (! empty($comment->snapshot_id)) {
            $snapshot = Snapshot::query()->findOrFail($comment->snapshot_id);
            $version = SnapshotVersion::query()
                ->where('snapshot_id', $snapshot->id)
                ->orderByDesc('revision')
                ->first();
            if ($version !== null) {
                return ['snapshot' => $snapshot, 'version' => $version];
            }
        } else {
            $snapshot = Snapshot::factory()->create();
        }

        $version = SnapshotVersioning::append(
            snapshot: $snapshot,
            viewType: 'report',
            dataPayload: ['blocks' => [
                ['type' => 'markdown', 'body' => 'Anchor target.'],
            ]],
        );

        return ['snapshot' => $snapshot, 'version' => $version];
    }
}
