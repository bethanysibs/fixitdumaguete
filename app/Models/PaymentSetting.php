<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'name',
    'deposit_type',
    'deposit_value',
    'minimum_deposit_amount',
    'maximum_deposit_amount',
    'is_active',
    'effective_at',
    'created_by',
])]
class PaymentSetting extends Model
{
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'deposit_value' => 'decimal:2',
            'minimum_deposit_amount' => 'decimal:2',
            'maximum_deposit_amount' => 'decimal:2',
            'is_active' => 'boolean',
            'effective_at' => 'datetime',
        ];
    }
}
