<?php

namespace App\Support;

class Money
{
    public static function commission(string $amount, string $rate): string
    {
        $commission = intdiv(self::toCents($amount) * self::toCents($rate), 10000);

        return self::fromCents($commission);
    }

    public static function subtract(string $amount, string $other): string
    {
        return self::fromCents(self::toCents($amount) - self::toCents($other));
    }

    public static function multiply(string $quantity, string $unitPrice): string
    {
        return self::fromCents(intdiv(self::toCents($quantity) * self::toCents($unitPrice), 100));
    }

    /**
     * @param  list<string>  $amounts
     */
    public static function sum(array $amounts): string
    {
        $cents = 0;

        foreach ($amounts as $amount) {
            $cents += self::toCents($amount);
        }

        return self::fromCents($cents);
    }

    public static function format(string $amount): string
    {
        $cents = self::toCents($amount);
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return $sign.'₱'.number_format(intdiv($cents, 100)).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function toCents(string $amount): int
    {
        $negative = str_starts_with($amount, '-');
        $amount = ltrim($amount, '-');

        if (! str_contains($amount, '.')) {
            $amount .= '.00';
        }

        [$whole, $fraction] = explode('.', $amount, 2);
        $fraction = substr(str_pad($fraction, 2, '0'), 0, 2);
        $cents = ((int) $whole * 100) + (int) $fraction;

        return $negative ? -$cents : $cents;
    }

    public static function fromCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return $sign.intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function normalize(string $amount): string
    {
        $negative = str_starts_with($amount, '-');
        $amount = ltrim($amount, '-');

        if (! str_contains($amount, '.')) {
            $amount .= '.00';
        }

        [$whole, $fraction] = explode('.', $amount, 2);

        return ($negative ? '-' : '').((int) $whole).'.'.str_pad(substr($fraction, 0, 2), 2, '0');
    }

    /**
     * @return array{amount: string, adjustment: string|null}
     */
    public static function bound(string $amount, ?string $minimum, ?string $maximum): array
    {
        $original = self::toCents($amount);
        $cents = $original;

        if ($minimum !== null && $cents < self::toCents($minimum)) {
            $cents = self::toCents($minimum);
        }

        if ($maximum !== null && $cents > self::toCents($maximum)) {
            $cents = self::toCents($maximum);
        }

        $adjustment = $cents - $original;

        return [
            'amount' => self::fromCents($cents),
            'adjustment' => $adjustment === 0 ? null : self::fromCents($adjustment),
        ];
    }
}
