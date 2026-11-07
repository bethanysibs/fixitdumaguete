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

    /**
     * @return array{balance_room_cents: int, deposit_room_cents: int, open_claim_cents: int}
     */
    public function claimRoom(Booking $booking): array
    {
        $summary = $this->summarize($booking);
        $empty = [
            'balance_room_cents' => 0,
            'deposit_room_cents' => 0,
            'open_claim_cents' => 0,
        ];

        if ($summary['quotation_id'] === null || $summary['balance_outstanding_cents'] === null) {
            return $empty;
        }

        $quotation = Quotation::query()->find($summary['quotation_id']);

        if ($quotation === null) {
            return $empty;
        }

        $open = $this->openClaimCents($booking, $quotation);
        $openDeposit = $this->openClaimCents($booking, $quotation, 'deposit');

        $balanceRoom = max(0, $summary['balance_outstanding_cents'] - $open);

        return [
            'balance_room_cents' => $balanceRoom,
            'deposit_room_cents' => min($balanceRoom, max(0, $summary['deposit_outstanding_cents'] - $openDeposit)),
            'open_claim_cents' => $open,
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

    private function openClaimCents(Booking $booking, Quotation $quotation, ?string $type = null): int
    {
        return (int) $booking->payments()
            ->where('quotation_id', $quotation->id)
            ->where('quotation_version', $quotation->version_number)
            ->whereIn('status', ['pending', 'submitted'])
            ->whereNotIn('verification_status', ['rejected', 'verified'])
            ->when($type !== null, fn ($query) => $query->where('payment_type', $type))
            ->get()
            ->sum(fn (Payment $payment) => Money::toCents((string) $payment->amount));
    }
}
