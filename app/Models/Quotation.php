<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'booking_id',
    'status',
    'currency',
    'notes',
    'created_by',
])]
class Quotation extends Model
{
    public const STATUSES = [
        'draft',
        'submitted',
        'superseded',
        'approved',
        'rejected',
        'expired',
    ];

    protected static function booted(): void
    {
        static::saving(function (Quotation $quotation): void {
            $quotation->current_booking_id = $quotation->is_current ? $quotation->booking_id : null;
        });
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'submitted' => 'Submitted',
            'superseded' => 'Superseded',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'expired' => 'Expired',
            default => $this->status,
        };
    }

    public function formattedSubtotal(): string
    {
        return Money::format((string) $this->subtotal);
    }

    public function formattedTotal(): string
    {
        return Money::format((string) $this->total_amount);
    }

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'subtotal' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'is_current' => 'boolean',
            'submitted_at' => 'datetime',
        ];
    }
}
