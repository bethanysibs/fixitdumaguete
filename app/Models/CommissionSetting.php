<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'effective_at',
    'first_booking_rate',
    'repeat_booking_rate',
    'basis',
    'minimum_amount',
    'maximum_amount',
    'created_by',
])]
class CommissionSetting extends Model
{
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function categoryRates(): HasMany
    {
        return $this->hasMany(CommissionCategoryRate::class);
    }

    public function workerRates(): HasMany
    {
        return $this->hasMany(CommissionWorkerRate::class);
    }

    public function basisLabel(): string
    {
        return match ($this->basis) {
            'labor' => 'Labor amount',
            'labor_and_charges' => 'Labor plus service charges',
            default => 'Entire service amount',
        };
    }

    protected function casts(): array
    {
        return [
            'effective_at' => 'datetime',
            'first_booking_rate' => 'decimal:2',
            'repeat_booking_rate' => 'decimal:2',
            'minimum_amount' => 'decimal:2',
            'maximum_amount' => 'decimal:2',
        ];
    }
}
