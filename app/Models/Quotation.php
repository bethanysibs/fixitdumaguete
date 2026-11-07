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
        'admin_approved',
        'rejected',
        'customer_rejected',
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

    public function sourceQuotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'source_quotation_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_approved_by');
    }

    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function customerAccepter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_approved_by');
    }

    public function customerRejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_rejected_by');
    }

    public function visibleToCustomer(): bool
    {
        return in_array($this->status, ['admin_approved', 'approved', 'customer_rejected'], true);
    }

    public function canBeRevisedByWorker(): bool
    {
        if (! $this->is_current) {
            return false;
        }

        if (in_array($this->status, ['rejected', 'customer_rejected'], true)) {
            return true;
        }

        return $this->status === 'submitted' && ! $this->is_admin_adjustment;
    }

    public function customerStatusLabel(): string
    {
        return match ($this->status) {
            'admin_approved' => 'Ready for your review',
            'approved' => 'Accepted',
            'customer_rejected' => 'Declined',
            default => $this->statusLabel(),
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'submitted' => 'Submitted',
            'superseded' => 'Superseded',
            'approved' => 'Accepted by the customer',
            'admin_approved' => 'Approved for customer review',
            'rejected' => 'Rejected',
            'customer_rejected' => 'Declined by the customer',
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
            'is_admin_adjustment' => 'boolean',
            'submitted_at' => 'datetime',
            'admin_approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'customer_approved_at' => 'datetime',
            'customer_rejected_at' => 'datetime',
        ];
    }
}
