<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Utility class for handling monetary values and currency conversions.
 *
 * This class follows the "Cents" pattern to avoid floating-point precision issues
 * when performing calculations or storing values in the database.
 */
final class Money
{
    /**
     * Converts various numeric formats into an integer representation in cents.
     *
     * Supports:
     * - Integers (multiplied by 100)
     * - Floats (rounded to the nearest cent)
     * - Numeric strings (e.g., "12.50", "-5.00")
     *
     * @param  mixed  $amount  The value to convert.
     * @return int The amount in cents.
     *
     * @throws InvalidArgumentException If the provided string is not a valid numeric format.
     */
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

    /**
     * Converts a cents integer into a formatted decimal string.
     *
     * Example: 1250 becomes "12.50".
     *
     * @param  mixed  $cents  The amount in cents.
     * @return string Formatted string "XX.YY".
     */
    public static function fromCents(mixed $cents): string
    {
        $cents = (int) ($cents ?? 0);
        $prefix = $cents < 0 ? '-' : '';
        $absolute = abs($cents);

        return sprintf('%s%d.%02d', $prefix, intdiv($absolute, 100), $absolute % 100);
    }

    /**
     * Converts a cents integer into a float for calculations or display.
     *
     * @param  mixed  $cents  The amount in cents.
     * @return float The amount as a float (e.g., 12.5).
     */
    public static function floatFromCents(mixed $cents): float
    {
        return (float) self::fromCents($cents);
    }
}
