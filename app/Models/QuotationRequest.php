<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'booking_id',
    'request_number',
    'title',
    'description',
    'status',
    'created_by',
])]
class QuotationRequest extends Model
{
    public const STATUSES = [
        'open',
        'canceled',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class)->orderBy('version_number')->orderBy('id');
    }

    public function currentQuotation(): HasOne
    {
        return $this->hasOne(Quotation::class)->where('is_current', true);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'open' => 'Open',
            'canceled' => 'Canceled',
            default => $this->status,
        };
    }
}
