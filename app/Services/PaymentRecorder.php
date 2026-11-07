<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\User;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PaymentRecorder
{
    public function record(
        Booking $booking,
        int $quotationId,
        int $versionNumber,
        string $amount,
        string $type,
        string $method,
        User $actor,
    ): Payment {
        return DB::transaction(function () use ($booking, $quotationId, $versionNumber, $amount, $type, $method, $actor) {
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->first();

            if ($locked === null) {
                throw ValidationException::withMessages([
                    'booking_id' => 'Choose a visit before recording a payment.',
                ]);
            }

            if ($actor->role !== 'customer' || (int) $actor->id !== (int) $locked->customer_id) {
                throw new AuthorizationException('Only the customer who booked this visit can record a payment.');
            }

            $quotation = Quotation::query()->whereKey($quotationId)->lockForUpdate()->first();

            if ($quotation === null || (int) $quotation->booking_id !== (int) $locked->id) {
                throw ValidationException::withMessages([
                    'quotation_id' => 'Choose the accepted quotation for this visit.',
                ]);
            }

            if ((int) $quotation->version_number !== $versionNumber) {
                throw ValidationException::withMessages([
                    'quotation_version' => 'The quotation version does not match the accepted version.',
                ]);
            }

            if ($quotation->status !== 'approved' || $quotation->customer_approved_at === null) {
                throw ValidationException::withMessages([
                    'quotation_id' => 'The customer has not accepted this quotation.',
                ]);
            }

            if (! in_array($type, Payment::TYPES, true)) {
                throw ValidationException::withMessages([
                    'payment_type' => 'Choose a deposit, balance, milestone, full payment, or adjustment.',
                ]);
            }

            if (! in_array($method, Payment::METHODS, true)) {
                throw ValidationException::withMessages([
                    'method' => 'Choose a payment method.',
                ]);
            }

            if (! preg_match('/^\d{1,10}(\.\d{1,2})?$/', $amount)) {
                throw ValidationException::withMessages([
                    'amount' => 'Enter an amount with up to two decimal places.',
                ]);
            }

            $amount = Money::normalize($amount);
            $cents = Money::toCents($amount);

            if ($cents <= 0 || $cents > Money::toCents((string) $quotation->total_amount)) {
                throw ValidationException::withMessages([
                    'amount' => 'Enter a payment amount that does not exceed the accepted quotation.',
                ]);
            }

            $snapshot = $this->financial($locked);
            $total = (string) $quotation->total_amount;
            $acceptedAt = $quotation->customer_approved_at->toDateTimeString();

            $payment = new Payment;
            $payment->forceFill([
                'booking_id' => $locked->id,
                'customer_id' => $locked->customer_id,
                'quotation_id' => $quotation->id,
                'quotation_version' => $quotation->version_number,
                'payment_type' => $type,
                'method' => $method,
                'status' => 'pending',
                'verification_status' => 'not_submitted',
                'amount' => $amount,
                'currency' => $quotation->currency ?: 'PHP',
                'submitted_by' => $actor->id,
            ])->save();

            $locked->refresh();
            $quotation->refresh();

            if ($this->financial($locked) !== $snapshot
                || (string) $quotation->total_amount !== $total
                || $quotation->status !== 'approved'
                || $quotation->customer_approved_at?->toDateTimeString() !== $acceptedAt
            ) {
                throw new RuntimeException('A payment changed the visit or the accepted quotation.');
            }

            return $payment->refresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function financial(Booking $booking): array
    {
        return $booking->only([
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
        ]);
    }
}
