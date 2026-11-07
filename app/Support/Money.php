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
}
