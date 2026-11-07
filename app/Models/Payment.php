<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['booking_id', 'customer_id', 'amount', 'status', 'method', 'paid_at'])]
class Payment extends Model
{
    public const TYPES = ['deposit', 'balance', 'milestone', 'full_payment', 'adjustment'];

    public const METHODS = ['gcash', 'bank_transfer', 'cash', 'gateway', 'other'];

    public const STATUSES = [
        'pending',
        'submitted',
        'verified',
        'rejected',
        'cancelled',
        'refunded',
        'partially_refunded',
        'paid',
    ];

    public const VERIFICATION_STATUSES = ['not_submitted', 'pending_review', 'verified', 'rejected'];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function proofs(): HasMany
    {
        return $this->hasMany(PaymentProof::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PaymentLog::class)->orderBy('id');
    }

    public function countsAsVerified(): bool
    {
        return in_array($this->status, ['verified', 'paid'], true)
            && $this->verification_status === 'verified';
    }

    public function formattedAmount(): string
    {
        return Money::format((string) $this->amount);
    }

    protected static function booted(): void
    {
        static::saving(function (Payment $payment): void {
            if ($payment->quotation_id === null) {
                return;
            }

            $quotation = Quotation::query()->find($payment->quotation_id);

            if ($quotation === null
                || $quotation->status !== 'approved'
                || $quotation->customer_approved_at === null
                || (int) $quotation->booking_id !== (int) $payment->booking_id
                || (int) $quotation->version_number !== (int) $payment->quotation_version
            ) {
                throw ValidationException::withMessages([
                    'quotation_id' => 'A payment must use the accepted quotation version.',
                ]);
            }
        });

        static::created(function (Payment $payment): void {
            $payment->writeLog('recorded', null, $payment->auditValues($payment->getAttributes()));
        });

        static::updated(function (Payment $payment): void {
            $changes = collect($payment->getChanges())->except(['updated_at']);

            if ($changes->isEmpty()) {
                return;
            }

            $previous = [];

            foreach ($changes->keys() as $key) {
                $previous[$key] = $payment->getOriginal($key);
            }

            $payment->writeLog('updated', $previous, $changes->all());
        });
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>  $new
     */
    private function writeLog(string $action, ?array $old, array $new): void
    {
        PaymentLog::query()->create([
            'payment_id' => $this->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'old_values' => $old,
            'new_values' => $this->auditValues($new),
            'description' => $action === 'recorded' ? 'Payment recorded.' : 'Payment record updated.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function auditValues(array $values): array
    {
        $kept = array_intersect_key($values, array_flip([
            'booking_id',
            'quotation_id',
            'quotation_version',
            'payment_type',
            'method',
            'status',
            'verification_status',
            'amount',
            'currency',
            'payment_reference',
            'submitted_by',
            'verified_by',
            'verified_at',
            'rejected_by',
            'rejected_at',
            'rejection_reason',
            'notes',
        ]));

        foreach ($kept as $key => $value) {
            if ($value instanceof \DateTimeInterface) {
                $kept[$key] = $value->format('Y-m-d H:i:s');
            }
        }

        return $kept;
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'quotation_version' => 'integer',
            'paid_at' => 'datetime',
            'verified_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }
}
