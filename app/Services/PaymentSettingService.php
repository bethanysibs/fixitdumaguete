<?php

namespace App\Services;

use App\Models\PaymentSetting;
use App\Models\User;
use App\Support\Money;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentSettingService
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function record(User $actor, array $input): PaymentSetting
    {
        if (! $actor->isAdmin()) {
            throw new AuthorizationException('Only an administrator can change payment requirements.');
        }

        $setting = $this->validated($input);
        $setting['created_by'] = $actor->id;

        return DB::transaction(function () use ($setting) {
            return PaymentSetting::query()->create($setting);
        });
    }

    public function applicableAt(DateTimeInterface $moment): ?PaymentSetting
    {
        return PaymentSetting::query()
            ->where('effective_at', '<=', $moment)
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validated(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));

        if ($name === '' || strlen($name) > 120) {
            throw ValidationException::withMessages([
                'name' => 'Enter a payment setting name of 120 characters or fewer.',
            ]);
        }

        $type = $input['deposit_type'] ?? null;

        if (! in_array($type, ['percentage', 'fixed'], true)) {
            throw ValidationException::withMessages([
                'deposit_type' => 'Choose a percentage deposit or a fixed deposit.',
            ]);
        }

        $value = $this->amount($input['deposit_value'] ?? null, 'deposit_value');

        if ($type === 'percentage' && Money::toCents($value) > 10000) {
            throw ValidationException::withMessages([
                'deposit_value' => 'Enter a deposit percentage from 0 to 100.',
            ]);
        }

        $minimum = $this->amountOrNull($input['minimum_deposit_amount'] ?? null, 'minimum_deposit_amount');
        $maximum = $this->amountOrNull($input['maximum_deposit_amount'] ?? null, 'maximum_deposit_amount');

        if ($minimum !== null && $maximum !== null && Money::toCents($maximum) < Money::toCents($minimum)) {
            throw ValidationException::withMessages([
                'maximum_deposit_amount' => 'The maximum deposit must be at least the minimum.',
            ]);
        }

        if (! is_bool($input['is_active'] ?? null)) {
            throw ValidationException::withMessages([
                'is_active' => 'Choose whether this deposit requirement is active.',
            ]);
        }

        $effectiveAt = $input['effective_at'] ?? null;

        if (! is_string($effectiveAt) && ! $effectiveAt instanceof DateTimeInterface) {
            throw ValidationException::withMessages([
                'effective_at' => 'Choose the date this payment requirement starts.',
            ]);
        }

        try {
            $effectiveAt = Carbon::parse($effectiveAt);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'effective_at' => 'Choose the date this payment requirement starts.',
            ]);
        }

        return [
            'name' => $name,
            'deposit_type' => $type,
            'deposit_value' => $value,
            'minimum_deposit_amount' => $minimum,
            'maximum_deposit_amount' => $maximum,
            'is_active' => $input['is_active'],
            'effective_at' => $effectiveAt,
        ];
    }

    private function amount(mixed $value, string $field): string
    {
        if (! is_string($value) && ! is_int($value)) {
            throw ValidationException::withMessages([
                $field => 'Enter an amount with up to two decimal places.',
            ]);
        }

        $value = (string) $value;

        if (! preg_match('/^\d{1,10}(\.\d{1,2})?$/', $value)) {
            throw ValidationException::withMessages([
                $field => 'Enter an amount with up to two decimal places.',
            ]);
        }

        return Money::normalize($value);
    }

    private function amountOrNull(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->amount($value, $field);
    }
}
