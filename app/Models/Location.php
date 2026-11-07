<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'slug', 'province', 'summary', 'is_active', 'map_latitude', 'map_longitude'])]
class Location extends Model
{
    public function workerProfiles(): BelongsToMany
    {
        return $this->belongsToMany(WorkerProfile::class);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'map_latitude' => 'decimal:7',
            'map_longitude' => 'decimal:7',
        ];
    }
}
