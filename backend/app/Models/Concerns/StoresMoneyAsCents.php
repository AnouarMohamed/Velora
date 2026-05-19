<?php

namespace App\Models\Concerns;

use App\Support\Money;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait StoresMoneyAsCents
{
    /** @return Attribute<mixed, mixed> */
    protected function moneyAttribute(string $centsColumn)
    {
        return Attribute::make(
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
    }
}
