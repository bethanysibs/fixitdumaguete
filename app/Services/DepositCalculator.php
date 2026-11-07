<?php

namespace App\Services;

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
