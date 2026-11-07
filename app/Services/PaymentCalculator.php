<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Quotation;
use App\Support\Money;
use Illuminate\Support\Collection;

class PaymentCalculator
{
    public function __construct(
        private DepositCalculator $deposits,
        private ContractAmountCalculator $contracts,
    ) {}

    /**
     * Payments are settled against the whole booking contract.
     * Only verified payments reduce the outstanding balance.
     * Pending claims reserve room for the next submission and do not reduce that balance.
     *
     * @return array{
     *     quotation_id: null,
     *     quotation_version: null,
     *     accepted_amount_cents: int,
     *     contract_cents: int,
     *     verified_cents: int,
     *     pending_cents: int,
     *     rejected_cents: int,
     *     recorded_cents: int,
     *     required_deposit_cents: int,
     *     original_deposit_cents: int,
     *     additional_deposit_cents: int,
     *     deposit_outstanding_cents: int,
     *     balance_outstanding_cents: int,
     *     eligible_cents: int,
     *     requirements_met: bool,
     *     paid_in_full: bool,
     *     accepted_count: int
     * }
     */
    public function summarize(Booking $booking): array
    {
        $booking->unsetRelation('payments');
        $contract = $this->contracts->summarize($booking);
        $deposit = $this->deposits->forBooking($booking);
        $verified = $this->sum($booking, fn (Payment $payment) => $payment->countsAsVerified());
        $pending = $this->sum($booking, fn (Payment $payment) => $this->isOpenClaim($payment));
        $rejected = $this->sum($booking, fn (Payment $payment) => $payment->status === 'rejected' || $payment->verification_status === 'rejected');
        $recorded = $this->sum($booking, fn (Payment $payment) => $payment->status === 'paid' && ! $payment->countsAsVerified());
        $contractCents = $contract['contract_cents'];
        $outstanding = max(0, $contractCents - $verified);
        $required = $deposit['final_deposit_cents'];
        $depositOutstanding = min($outstanding, max(0, $required - $verified));

        return [
            'quotation_id' => null,
            'quotation_version' => null,
            'accepted_amount_cents' => $contractCents,
            'contract_cents' => $contractCents,
            'verified_cents' => $verified,
            'pending_cents' => $pending,
            'rejected_cents' => $rejected,
            'recorded_cents' => $recorded,
            'required_deposit_cents' => $required,
            'original_deposit_cents' => $deposit['original_deposit_cents'],
            'additional_deposit_cents' => $deposit['additional_deposit_cents'],
            'deposit_outstanding_cents' => $depositOutstanding,
            'balance_outstanding_cents' => $outstanding,
            'eligible_cents' => max(0, $outstanding - $pending),
            'requirements_met' => $verified >= $required,
            'paid_in_full' => $contractCents > 0 && $verified >= $contractCents,
            'accepted_count' => count($contract['accepted_lines']),
        ];
    }

    /**
     * Current customer-accepted quotations. Kept so older payment records can still
     * point at one quotation. New claims are not limited to a single quotation.
     *
     * @return Collection<int, Quotation>
     */
    public function currentAccepted(Booking $booking, bool $lock = false): Collection
    {
        $query = Quotation::query()
            ->where('booking_id', $booking->id)
            ->where('is_current', true)
            ->where('status', 'approved')
            ->whereNotNull('customer_approved_at')
            ->whereHas('quotationRequest', fn ($requests) => $requests->where('status', 'open'))
            ->orderBy('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    /**
     * @return array{balance_room_cents: int, deposit_room_cents: int, open_claim_cents: int}
     */
    public function claimRoom(Booking $booking): array
    {
        $summary = $this->summarize($booking);
        $openDeposit = $this->sum(
            $booking,
            fn (Payment $payment) => $this->isOpenClaim($payment) && $payment->payment_type === 'deposit',
        );

        return [
            'balance_room_cents' => $summary['eligible_cents'],
            'deposit_room_cents' => min($summary['eligible_cents'], max(0, $summary['deposit_outstanding_cents'] - $openDeposit)),
            'open_claim_cents' => $summary['pending_cents'],
        ];
    }

    private function isOpenClaim(Payment $payment): bool
    {
        return in_array($payment->status, ['pending', 'submitted'], true)
            && ! in_array($payment->verification_status, ['rejected', 'verified'], true);
    }

    private function sum(Booking $booking, callable $include): int
    {
        return (int) $booking->payments->sum(function (Payment $payment) use ($include) {
            return $include($payment) ? Money::toCents((string) $payment->amount) : 0;
        });
    }
}
