<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tool_name', 'duration_ms', 'status', 'payload_bytes', 'user_id', 'workbench_id'])]
class McpCallLog extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_ms' => 'integer',
            'payload_bytes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Workbench, $this>
     */
    public function workbench(): BelongsTo
    {
        return $this->belongsTo(Workbench::class);
    }
}
