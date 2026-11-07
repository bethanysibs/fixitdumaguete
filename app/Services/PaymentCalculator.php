<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Quotation;
use App\Support\Money;

class PaymentCalculator
{
    public function __construct(private DepositCalculator $deposits) {}

    /**
     * @return array{
     *     quotation_id: int|null,
     *     quotation_version: int|null,
     *     accepted_amount_cents: int|null,
     *     verified_cents: int,
     *     required_deposit_cents: int,
     *     deposit_outstanding_cents: int,
     *     balance_outstanding_cents: int|null,
     *     requirements_met: bool,
     *     paid_in_full: bool
     * }
     */
    public function summarize(Booking $booking): array
    {
        $quotation = $booking->currentQuotation()->first();

        if ($quotation === null || $quotation->status !== 'approved' || $quotation->customer_approved_at === null) {
            return [
                'quotation_id' => null,
                'quotation_version' => null,
                'accepted_amount_cents' => null,
                'verified_cents' => 0,
                'required_deposit_cents' => 0,
                'deposit_outstanding_cents' => 0,
                'balance_outstanding_cents' => null,
                'requirements_met' => false,
                'paid_in_full' => false,
            ];
        }

        $deposit = $this->deposits->calculate($quotation);
        $verified = $this->verifiedCents($booking, $quotation);
        $required = $deposit['final_deposit_cents'];
        $accepted = $deposit['accepted_amount_cents'];

        return [
            'quotation_id' => (int) $quotation->id,
            'quotation_version' => (int) $quotation->version_number,
            'accepted_amount_cents' => $accepted,
            'verified_cents' => $verified,
            'required_deposit_cents' => $required,
            'deposit_outstanding_cents' => max(0, $required - $verified),
            'balance_outstanding_cents' => max(0, $accepted - $verified),
            'requirements_met' => $verified >= $required,
            'paid_in_full' => $verified >= $accepted,
        ];
    }

    public function verifiedCents(Booking $booking, Quotation $quotation): int
    {
        return (int) $booking->payments()
            ->where('quotation_id', $quotation->id)
            ->where('quotation_version', $quotation->version_number)
            ->get()
            ->sum(fn (Payment $payment) => $payment->countsAsVerified()
                ? Money::toCents((string) $payment->amount)
                : 0);
    }
}
