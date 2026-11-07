<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\QuotationRequest;
use App\Support\Money;

class ContractAmountCalculator
{
    /**
     * The original advertised rate is the price saved on the booking.
     * Accepted additional quotations are the current customer-accepted version of each open request.
     *
     * @return array{
     *     original_rate: string,
     *     original_rate_cents: int,
     *     accepted_additional: string,
     *     accepted_additional_cents: int,
     *     contract_amount: string,
     *     contract_cents: int,
     *     verified_payments: string,
     *     verified_cents: int,
     *     waiting_review: string,
     *     waiting_cents: int,
     *     remaining_payable: string,
     *     remaining_cents: int,
     *     accepted_lines: list<array{request_number: int, title: string, amount: string, amount_cents: int}>,
     *     requests: list<array<string, mixed>>
     * }
     */
    public function summarize(Booking $booking): array
    {
        $booking->loadMissing(['quotationRequests.quotations', 'payments']);

        $originalCents = Money::toCents((string) $booking->price_amount);
        $acceptedLines = [];
        $acceptedCents = 0;
        $requests = [];

        foreach ($booking->quotationRequests as $request) {
            $current = $request->quotations->firstWhere('is_current', true);
            $counted = $this->counts($request, $current);
            $amountCents = $current !== null ? Money::toCents((string) $current->total_amount) : 0;

            if ($counted) {
                $acceptedCents += $amountCents;
                $acceptedLines[] = [
                    'request_number' => (int) $request->request_number,
                    'title' => (string) $request->title,
                    'amount' => Money::fromCents($amountCents),
                    'amount_cents' => $amountCents,
                ];
            }

            $requests[] = [
                'id' => (int) $request->id,
                'request_number' => (int) $request->request_number,
                'title' => (string) $request->title,
                'description' => $request->description,
                'status' => (string) $request->status,
                'customer_visible' => $request->quotations->contains(fn (Quotation $quotation) => $quotation->visibleToCustomer()),
                'current_version' => $current?->version_number,
                'current_status' => $current?->status,
                'current_amount' => $current !== null ? (string) $current->total_amount : null,
                'counts_toward_contract' => $counted,
                'accepted_amount' => $counted ? Money::fromCents($amountCents) : null,
                'customer_approved_at' => $current?->customer_approved_at,
                'customer_rejected_at' => $current?->customer_rejected_at,
                'admin_approved_at' => $current?->admin_approved_at,
            ];
        }

        $verifiedCents = (int) $booking->payments->sum(
            fn (Payment $payment) => $payment->countsAsVerified() ? Money::toCents((string) $payment->amount) : 0,
        );
        $waitingCents = (int) $booking->payments
            ->filter(fn (Payment $payment) => in_array($payment->status, ['pending', 'submitted'], true)
                && ! in_array($payment->verification_status, ['rejected', 'verified'], true))
            ->sum(fn (Payment $payment) => Money::toCents((string) $payment->amount));
        $contractCents = $originalCents + $acceptedCents;

        return [
            'original_rate' => Money::fromCents($originalCents),
            'original_rate_cents' => $originalCents,
            'accepted_additional' => Money::fromCents($acceptedCents),
            'accepted_additional_cents' => $acceptedCents,
            'contract_amount' => Money::fromCents($contractCents),
            'contract_cents' => $contractCents,
            'verified_payments' => Money::fromCents($verifiedCents),
            'verified_cents' => $verifiedCents,
            'waiting_review' => Money::fromCents($waitingCents),
            'waiting_cents' => $waitingCents,
            'remaining_payable' => Money::fromCents(max(0, $contractCents - $verifiedCents)),
            'remaining_cents' => max(0, $contractCents - $verifiedCents),
            'accepted_lines' => $acceptedLines,
            'requests' => $requests,
        ];
    }

    private function counts(QuotationRequest $request, ?Quotation $current): bool
    {
        return $request->status === 'open'
            && $current !== null
            && $current->is_current
            && $current->status === 'approved'
            && $current->customer_approved_at !== null;
    }
}
