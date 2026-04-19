<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WorkbenchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'name'])]
class Workbench extends Model
{
    /** @use HasFactory<WorkbenchFactory> */
    use HasFactory;

    /**
     * @return HasMany<Snapshot, $this>
     */
    public function snapshots(): HasMany
    {
        return $this->hasMany(Snapshot::class);
    }
}
