<?php

namespace App\Models;

use App\Services\PaymentCalculator;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'customer_id',
    'worker_profile_id',
    'service_id',
    'location_id',
    'scheduled_at',
    'notes',
    'latitude',
    'longitude',
    'address',
    'landmark',
    'contact_phone',
    'status',
    'price_amount',
    'commission_rate',
    'commission_amount',
    'worker_amount',
    'commission_setting_id',
    'commission_rule',
    'commission_basis',
    'commission_base_amount',
    'commission_original_rate',
    'commission_adjustment',
])]
class Booking extends Model
{
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function workerProfile(): BelongsTo
    {
        return $this->belongsTo(WorkerProfile::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('id');
    }

    public function verifiedPaymentTotal(): string
    {
        return Money::fromCents(app(PaymentCalculator::class)->summarize($this)['verified_cents']);
    }

    public function outstandingBalance(): ?string
    {
        $cents = app(PaymentCalculator::class)->summarize($this)['balance_outstanding_cents'];

        return $cents === null ? null : Money::fromCents($cents);
    }

    public function commissionSetting(): BelongsTo
    {
        return $this->belongsTo(CommissionSetting::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(BookingPhoto::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class)->orderBy('version_number');
    }

    public function currentQuotation(): HasOne
    {
        return $this->hasOne(Quotation::class)->where('is_current', true);
    }

    public function statusLabel(): string
    {
        return config('marketplace.statuses.'.$this->status, $this->status);
    }

    public function allowsNewQuotation(): bool
    {
        return in_array($this->status, ['pending', 'confirmed', 'in_progress'], true);
    }

    public function allowsQuotationEdits(): bool
    {
        return $this->allowsNewQuotation() || $this->status === 'completed';
    }

    public function formattedPrice(): string
    {
        return Money::format((string) $this->price_amount);
    }

    public function commissionRuleLabel(): string
    {
        return match ($this->commission_rule) {
            'worker' => 'Worker rate',
            'category' => 'Category rate',
            'repeat' => 'Repeat booking',
            'first' => 'First booking',
            default => 'Saved with the booking',
        };
    }

    public function visibleTo(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->isAdmin()
            || $user->id === $this->customer_id
            || $user->id === $this->workerProfile?->user_id;
    }

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'price_amount' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'worker_amount' => 'decimal:2',
            'commission_base_amount' => 'decimal:2',
            'commission_original_rate' => 'decimal:2',
            'commission_adjustment' => 'decimal:2',
        ];
    }
}
