<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\CommissionSetting;
use App\Support\Money;

class CommissionCalculator
{
    /**
     * @return array{
     *     setting_id: int|null,
     *     rule: string,
     *     basis: string,
     *     base_amount: string,
     *     original_rate: string,
     *     adjustment: string|null,
     *     amount: string,
     *     worker_amount: string
     * }
     */
    public function quote(int $customerId, int $workerProfileId, int $categoryId, string $serviceAmount): array
    {
        $serviceAmount = Money::normalize($serviceAmount);
        $setting = $this->activeSetting();
        $rule = $this->rule($setting, $customerId, $workerProfileId, $categoryId);
        $rate = $rule['rate'] !== null
            ? Money::normalize($rule['rate'])
            : Money::normalize((string) config('marketplace.commission_rate'));
        $base = $serviceAmount;
        // Bookings have no labor or service-charge lines yet, so the snapshot
        // records the service price even if an older version names another basis.
        $basis = 'entire_service';
        $calculated = Money::commission($base, $rate);
        $bounded = Money::bound(
            $calculated,
            $setting?->minimum_amount !== null ? (string) $setting->minimum_amount : null,
            $setting?->maximum_amount !== null ? (string) $setting->maximum_amount : null,
        );

        $finalCents = Money::toCents($bounded['amount']);
        $serviceCents = Money::toCents($serviceAmount);

        if ($finalCents > $serviceCents) {
            $finalCents = $serviceCents;
        }

        $adjustmentCents = $finalCents - Money::toCents($calculated);

        return [
            'setting_id' => $setting?->id,
            'rule' => $rule['name'],
            'basis' => $basis,
            'base_amount' => $base,
            'original_rate' => $rate,
            'adjustment' => $adjustmentCents === 0 ? null : Money::fromCents($adjustmentCents),
            'amount' => Money::fromCents($finalCents),
            'worker_amount' => Money::fromCents($serviceCents - $finalCents),
        ];
    }

    public function hasCompletedPaidBooking(int $customerId, int $workerProfileId): bool
    {
        return Booking::query()
            ->where('customer_id', $customerId)
            ->where('worker_profile_id', $workerProfileId)
            ->where('status', 'completed')
            ->whereHas('payment', fn ($query) => $query->where('method', 'platform')->where('status', 'paid'))
            ->whereDoesntHave('payment', fn ($query) => $query->where('method', 'platform')->where('status', 'refunded'))
            ->exists();
    }

    private function activeSetting(): ?CommissionSetting
    {
        return CommissionSetting::query()
            ->where('effective_at', '<=', now())
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->with(['categoryRates', 'workerRates'])
            ->first();
    }

    /**
     * @return array{name: string, rate: string|null}
     */
    private function rule(?CommissionSetting $setting, int $customerId, int $workerProfileId, int $categoryId): array
    {
        if ($setting === null) {
            return ['name' => 'first', 'rate' => null];
        }

        $workerRate = $setting->workerRates->firstWhere('worker_profile_id', $workerProfileId);

        if ($workerRate !== null) {
            return ['name' => 'worker', 'rate' => (string) $workerRate->rate];
        }

        $categoryRate = $setting->categoryRates->firstWhere('service_category_id', $categoryId);

        if ($categoryRate !== null) {
            return ['name' => 'category', 'rate' => (string) $categoryRate->rate];
        }

        if ($this->hasCompletedPaidBooking($customerId, $workerProfileId)) {
            return ['name' => 'repeat', 'rate' => (string) $setting->repeat_booking_rate];
        }

        return ['name' => 'first', 'rate' => (string) $setting->first_booking_rate];
    }
}
