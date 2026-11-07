<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\PaymentSetting;
use App\Models\Quotation;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

class DepositCalculator
{
    public function __construct(private PaymentSettingService $settings) {}

    /**
     * @return array{
     *     quotation_id: int,
     *     quotation_version: int,
     *     accepted_amount_cents: int,
     *     setting_id: int|null,
     *     requirement: string,
     *     deposit_type: string|null,
     *     deposit_value: string|null,
     *     minimum_deposit_cents: int|null,
     *     maximum_deposit_cents: int|null,
     *     raw_deposit_cents: int,
     *     minimum_adjustment_cents: int,
     *     maximum_adjustment_cents: int,
     *     quotation_cap_adjustment_cents: int,
     *     final_deposit_cents: int,
     *     remaining_balance_cents: int
     * }
     */
    public function calculate(Quotation $quotation): array
    {
        if ($quotation->status !== 'approved' || $quotation->customer_approved_at === null) {
            throw ValidationException::withMessages([
                'quotation' => 'A deposit can be calculated only for the quotation the customer accepted.',
            ]);
        }

        $accepted = Money::toCents((string) $quotation->total_amount);
        $setting = $this->settings->applicableAt($quotation->customer_approved_at);
        $base = [
            'quotation_id' => (int) $quotation->id,
            'quotation_version' => (int) $quotation->version_number,
            'accepted_amount_cents' => $accepted,
            'setting_id' => $setting?->id,
            'deposit_type' => $setting?->deposit_type,
            'deposit_value' => $setting !== null ? (string) $setting->deposit_value : null,
            'minimum_deposit_cents' => $setting?->minimum_deposit_amount !== null
                ? Money::toCents((string) $setting->minimum_deposit_amount)
                : null,
            'maximum_deposit_cents' => $setting?->maximum_deposit_amount !== null
                ? Money::toCents((string) $setting->maximum_deposit_amount)
                : null,
        ];

        if ($setting === null) {
            return $base + $this->emptyDeposit('unconfigured', $accepted);
        }

        if (! in_array($setting->deposit_type, ['percentage', 'fixed'], true)) {
            throw ValidationException::withMessages([
                'deposit_type' => 'Choose a percentage deposit or a fixed deposit.',
            ]);
        }

        if (! $setting->is_active) {
            return $base + $this->emptyDeposit('disabled', $accepted);
        }

        $raw = $setting->deposit_type === 'percentage'
            ? Money::toCents(Money::commission((string) $quotation->total_amount, (string) $setting->deposit_value))
            : Money::toCents((string) $setting->deposit_value);

        $afterMinimum = $raw;
        $minimumAdjustment = 0;

        if ($base['minimum_deposit_cents'] !== null && $afterMinimum < $base['minimum_deposit_cents']) {
            $minimumAdjustment = $base['minimum_deposit_cents'] - $afterMinimum;
            $afterMinimum = $base['minimum_deposit_cents'];
        }

        $afterMaximum = $afterMinimum;
        $maximumAdjustment = 0;

        if ($base['maximum_deposit_cents'] !== null && $afterMaximum > $base['maximum_deposit_cents']) {
            $maximumAdjustment = $base['maximum_deposit_cents'] - $afterMaximum;
            $afterMaximum = $base['maximum_deposit_cents'];
        }

        $final = $afterMaximum;
        $capAdjustment = 0;

        if ($final > $accepted) {
            $capAdjustment = $accepted - $final;
            $final = $accepted;
        }

        if ($final < 0) {
            $final = 0;
        }

        return $base + [
            'requirement' => $setting->deposit_type,
            'raw_deposit_cents' => $raw,
            'minimum_adjustment_cents' => $minimumAdjustment,
            'maximum_adjustment_cents' => $maximumAdjustment,
            'quotation_cap_adjustment_cents' => $capAdjustment,
            'final_deposit_cents' => $final,
            'remaining_balance_cents' => $accepted - $final,
        ];
    }

    /**
     * Deposit for the whole booking contract.
     *
     * The original advertised rate keeps the deposit that applied when the visit was booked.
     * A fixed deposit is charged only on that original rate. Each later accepted quotation adds
     * only its own percentage, if the setting at acceptance is an active percentage. Minimums
     * are not applied again. The original maximum, when one exists, caps the combined deposit.
     * The result cannot exceed the contract amount.
     *
     * @return array{
     *     contract_cents: int,
     *     original_deposit_cents: int,
     *     additional_deposit_cents: int,
     *     final_deposit_cents: int,
     *     requirement: string
     * }
     */
    public function forBooking(Booking $booking): array
    {
        $contract = app(ContractAmountCalculator::class)->summarize($booking);
        $originalSetting = $this->settings->applicableAt($booking->created_at);
        $original = $this->calculatePortion($contract['original_rate_cents'], $originalSetting);
        $additional = 0;

        $accepted = Quotation::query()
            ->where('booking_id', $booking->id)
            ->where('is_current', true)
            ->where('status', 'approved')
            ->whereNotNull('customer_approved_at')
            ->whereHas('quotationRequest', fn ($query) => $query->where('status', 'open'))
            ->get();

        foreach ($accepted as $quotation) {
            $setting = $this->settings->applicableAt($quotation->customer_approved_at);

            if ($setting === null || ! $setting->is_active || $setting->deposit_type !== 'percentage') {
                continue;
            }

            $additional += Money::toCents(Money::commission(
                (string) $quotation->total_amount,
                (string) $setting->deposit_value,
            ));
        }

        $required = $original + $additional;
        $maximum = $originalSetting?->maximum_deposit_amount;

        if ($maximum !== null) {
            $required = min($required, Money::toCents((string) $maximum));
        }

        $required = min(max(0, $required), $contract['contract_cents']);

        return [
            'contract_cents' => $contract['contract_cents'],
            'original_deposit_cents' => min($original, $required),
            'additional_deposit_cents' => max(0, $required - min($original, $required)),
            'final_deposit_cents' => $required,
            'requirement' => $originalSetting === null
                ? 'unconfigured'
                : ($originalSetting->is_active ? (string) $originalSetting->deposit_type : 'disabled'),
        ];
    }

    private function calculatePortion(int $amountCents, ?PaymentSetting $setting): int
    {
        if ($setting === null || ! $setting->is_active) {
            return 0;
        }

        $amount = Money::fromCents($amountCents);
        $raw = $setting->deposit_type === 'percentage'
            ? Money::toCents(Money::commission($amount, (string) $setting->deposit_value))
            : Money::toCents((string) $setting->deposit_value);
        $minimum = $setting->minimum_deposit_amount !== null
            ? Money::toCents((string) $setting->minimum_deposit_amount)
            : null;
        $maximum = $setting->maximum_deposit_amount !== null
            ? Money::toCents((string) $setting->maximum_deposit_amount)
            : null;
        $final = $raw;

        if ($minimum !== null && $final < $minimum) {
            $final = $minimum;
        }

        if ($maximum !== null && $final > $maximum) {
            $final = $maximum;
        }

        return min(max(0, $final), $amountCents);
    }

    /**
     * @return array{
     *     requirement: string,
     *     raw_deposit_cents: int,
     *     minimum_adjustment_cents: int,
     *     maximum_adjustment_cents: int,
     *     quotation_cap_adjustment_cents: int,
     *     final_deposit_cents: int,
     *     remaining_balance_cents: int
     * }
     */
    private function emptyDeposit(string $requirement, int $accepted): array
    {
        return [
            'requirement' => $requirement,
            'raw_deposit_cents' => 0,
            'minimum_adjustment_cents' => 0,
            'maximum_adjustment_cents' => 0,
            'quotation_cap_adjustment_cents' => 0,
            'final_deposit_cents' => 0,
            'remaining_balance_cents' => $accepted,
        ];
    }
}
