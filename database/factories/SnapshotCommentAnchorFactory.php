<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SnapshotCommentAnchor;
use App\Models\SnapshotVersionComment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SnapshotCommentAnchor>
 */
class SnapshotCommentAnchorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'comment_id' => SnapshotVersionComment::factory(),
            'anchor_type' => 'element_id',
            'anchor_data' => ['element_id' => 'el-'.fake()->uuid()],
            'created_at' => now(),
        ];
    }

    public function cell(int $row, string $column): self
    {
        return $this->state(fn () => [
            'anchor_type' => 'cell',
            'anchor_data' => ['row' => $row, 'column' => $column],
        ]);
    }

    public function textRange(int $start, int $end): self
    {
        return $this->state(fn () => [
            'anchor_type' => 'text_range',
            'anchor_data' => ['start' => $start, 'end' => $end],
        ]);
    }
}
