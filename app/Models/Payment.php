<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['booking_id', 'customer_id', 'amount', 'status', 'method', 'paid_at'])]
class Payment extends Model
{
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function formattedAmount(): string
    {
        return Money::format((string) $this->amount);
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }
}
