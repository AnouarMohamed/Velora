<?php

namespace App\Models\Concerns;

use App\Support\Money;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Trait StoresMoneyAsCents
 *
 * Provides a reusable way to store monetary values as integer cents while
 * exposing API-compatible decimal strings through Eloquent accessors.
 */
trait StoresMoneyAsCents
{
    /**
     * Create a money attribute for a given cents column.
     *
     * This helper generates a Laravel accessor/mutator pair:
     * - get: convert integer cents to a decimal string.
     * - set: convert decimal input to integer cents.
     *
     * @param  string  $centsColumn  The name of the database column storing cents
     * @return Attribute<string|null, mixed>
     */
    protected function moneyCast(string $centsColumn)
    {
        /** @var Attribute<string|null, mixed> $attribute */
        $attribute = Attribute::make(
            get: function (mixed $value, array $attributes) use ($centsColumn): ?string {
                if (array_key_exists($centsColumn, $attributes)) {
                    return Money::fromCents($attributes[$centsColumn]);
                }

                if ($value !== null) {
                    return Money::fromCents(Money::toCents($value));
                }

                return null;
            },
            set: fn (mixed $value): array => [$centsColumn => Money::toCents($value)],
        );

        return $attribute;
    }
}
