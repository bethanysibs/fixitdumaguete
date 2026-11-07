<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\User;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PaymentRecorder
{
    public function __construct(
        private PaymentCalculator $claims,
        private PaymentInstructionCatalog $instructions,
        private PaymentProofStorage $proofs,
    ) {}

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

    public function submit(
        Booking $booking,
        User $actor,
        string $amount,
        string $type,
        string $method,
        string $reference,
        mixed $notes,
        ?UploadedFile $proof,
    ): Payment {
        return DB::transaction(function () use ($booking, $actor, $amount, $type, $method, $reference, $notes, $proof) {
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->first();

            if ($locked === null) {
                throw ValidationException::withMessages([
                    'booking_id' => 'Choose a visit before submitting a payment.',
                ]);
            }

            if ($actor->role !== 'customer' || (int) $actor->id !== (int) $locked->customer_id) {
                throw new AuthorizationException('Only the customer who booked this visit can submit a payment.');
            }

            if (! $locked->allowsNewQuotation()) {
                throw ValidationException::withMessages([
                    'booking_id' => 'This visit cannot take a payment claim.',
                ]);
            }

            Payment::query()->where('booking_id', $locked->id)->lockForUpdate()->get();
            $locked->unsetRelation('payments');

            if (! in_array($type, ['deposit', 'balance', 'full_payment'], true)) {
                throw ValidationException::withMessages([
                    'payment_type' => 'Choose a deposit, balance, or full payment.',
                ]);
            }

            if (! $this->instructions->allows($method)) {
                throw ValidationException::withMessages([
                    'method' => 'Choose an available payment method.',
                ]);
            }

            $reference = trim((string) preg_replace('/\s+/', ' ', $reference));

            if (! preg_match('/^[A-Za-z0-9]+(?:[ -][A-Za-z0-9]+)*$/', $reference) || strlen($reference) < 4 || strlen($reference) > 40) {
                throw ValidationException::withMessages([
                    'payment_reference' => 'Enter a reference number of 4 to 40 letters or numbers.',
                ]);
            }

            if ($notes !== null && ! is_string($notes)) {
                throw ValidationException::withMessages([
                    'notes' => 'Enter notes of 2,000 characters or fewer.',
                ]);
            }

            $notes = is_string($notes) ? trim($notes) : null;

            if ($notes === '') {
                $notes = null;
            }

            if ($notes !== null && strlen($notes) > 2000) {
                throw ValidationException::withMessages([
                    'notes' => 'Enter notes of 2,000 characters or fewer.',
                ]);
            }

            if ($proof === null) {
                throw ValidationException::withMessages([
                    'proof' => 'Upload a JPG, PNG, or PDF receipt of 5 MB or less.',
                ]);
            }

            $duplicate = Payment::query()
                ->where('booking_id', $locked->id)
                ->where('payment_type', $type)
                ->where('method', $method)
                ->where('payment_reference', $reference)
                ->whereIn('status', ['pending', 'submitted'])
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'payment_reference' => 'This payment claim was already submitted.',
                ]);
            }

            if (! preg_match('/^\d{1,10}(\.\d{1,2})?$/', $amount)) {
                throw ValidationException::withMessages([
                    'amount' => 'Enter an amount with up to two decimal places.',
                ]);
            }

            $amount = Money::normalize($amount);
            $cents = Money::toCents($amount);
            $room = $this->claims->claimRoom($locked);

            if ($cents <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Enter an amount greater than zero.',
                ]);
            }

            if ($cents > $room['balance_room_cents']) {
                throw ValidationException::withMessages([
                    'amount' => 'The amount is more than the unpaid balance.',
                ]);
            }

            if ($type === 'deposit' && ($room['deposit_room_cents'] <= 0 || $cents > $room['deposit_room_cents'])) {
                throw ValidationException::withMessages([
                    'amount' => 'A deposit cannot be more than the deposit still due.',
                ]);
            }

            if ($type === 'full_payment' && $cents !== $room['balance_room_cents']) {
                throw ValidationException::withMessages([
                    'amount' => 'A full payment must match the unpaid balance.',
                ]);
            }

            $snapshot = $this->financial($locked);
            $storedPath = null;

            try {
                $payment = new Payment;
                $payment->forceFill([
                    'booking_id' => $locked->id,
                    'customer_id' => $locked->customer_id,
                    'quotation_id' => null,
                    'quotation_version' => null,
                    'payment_type' => $type,
                    'method' => $method,
                    'status' => 'submitted',
                    'verification_status' => 'pending_review',
                    'amount' => $amount,
                    'currency' => 'PHP',
                    'payment_reference' => $reference,
                    'submitted_by' => $actor->id,
                    'submitted_at' => now(),
                    'notes' => $notes,
                    'verified_by' => null,
                    'verified_at' => null,
                    'paid_at' => null,
                ])->save();

                $storedPath = $this->proofs->store($payment, $proof, $actor)->file_path;

                $locked->refresh();

                if ($this->financial($locked) !== $snapshot
                    || $payment->status !== 'submitted'
                    || $payment->verification_status !== 'pending_review'
                    || $payment->verified_at !== null
                    || $payment->paid_at !== null
                ) {
                    throw new RuntimeException('A payment claim changed the visit or the accepted quotation.');
                }

                return $payment->refresh();
            } catch (\Throwable $exception) {
                if ($storedPath !== null) {
                    $this->proofs->delete($storedPath);
                }

                throw $exception;
            }
        });
    }

    public function verify(Payment $payment, User $actor): Payment
    {
        if (! $actor->isAdmin()) {
            throw new AuthorizationException('Only an administrator can check a payment.');
        }

        return DB::transaction(function () use ($payment, $actor) {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->first();

            if ($locked === null) {
                throw ValidationException::withMessages([
                    'payment' => 'Choose a payment to check.',
                ]);
            }

            if ($locked->countsAsVerified() || $locked->verification_status === 'verified') {
                throw ValidationException::withMessages([
                    'payment' => 'This payment was already checked.',
                ]);
            }

            if ($locked->status !== 'submitted' || $locked->verification_status !== 'pending_review') {
                throw ValidationException::withMessages([
                    'payment' => 'Only a receipt waiting for verification can be marked paid.',
                ]);
            }

            $locked->forceFill([
                'status' => 'verified',
                'verification_status' => 'verified',
                'verified_by' => $actor->id,
                'verified_at' => now(),
            ])->save();

            return $locked->refresh();
        });
    }

    public function reject(Payment $payment, User $actor, string $reason): Payment
    {
        if (! $actor->isAdmin()) {
            throw new AuthorizationException('Only an administrator can check a payment.');
        }

        $reason = trim($reason);

        if (strlen($reason) < 3 || strlen($reason) > 500) {
            throw ValidationException::withMessages([
                'rejection_reason' => 'Enter a reason of 3 to 500 characters.',
            ]);
        }

        return DB::transaction(function () use ($payment, $actor, $reason) {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->first();

            if ($locked === null) {
                throw ValidationException::withMessages([
                    'payment' => 'Choose a payment to check.',
                ]);
            }

            if ($locked->countsAsVerified() || $locked->verification_status === 'verified') {
                throw ValidationException::withMessages([
                    'payment' => 'A paid receipt cannot be rejected here.',
                ]);
            }

            if ($locked->status !== 'submitted' || $locked->verification_status !== 'pending_review') {
                throw ValidationException::withMessages([
                    'payment' => 'Only a receipt waiting for verification can be rejected.',
                ]);
            }

            $locked->forceFill([
                'status' => 'rejected',
                'verification_status' => 'rejected',
                'rejected_by' => $actor->id,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ])->save();

            return $locked->refresh();
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
