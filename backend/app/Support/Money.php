<?php

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    public static function toCents(mixed $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        if (is_int($amount)) {
            return $amount * 100;
        }

        if (is_float($amount)) {
            return (int) round($amount * 100);
        }

        $value = trim((string) $amount);

        if (! preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('Invalid money amount.');
        }

        $isNegative = str_starts_with($value, '-');
        $unsigned = ltrim($value, '-');
        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');

        $cents = ((int) $whole) * 100;
        $cents += (int) substr(str_pad($fraction, 2, '0'), 0, 2);

        if ((int) ($fraction[2] ?? 0) >= 5) {
            $cents++;
        }

        return $isNegative ? -$cents : $cents;
    }

    public static function fromCents(mixed $cents): string
    {
        $cents = (int) ($cents ?? 0);
        $prefix = $cents < 0 ? '-' : '';
        $absolute = abs($cents);

        return sprintf('%s%d.%02d', $prefix, intdiv($absolute, 100), $absolute % 100);
    }

    public static function floatFromCents(mixed $cents): float
    {
        return (float) self::fromCents($cents);
    }
}
