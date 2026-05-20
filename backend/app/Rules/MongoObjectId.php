<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Custom validation rule for MongoDB ObjectIDs.
 *
 * It ensures that a given value matches the 24-character hexadecimal format used by MongoDB.
 */
class MongoObjectId implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  string  $attribute  The name of the attribute being validated.
     * @param  mixed  $value  The value of the attribute.
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // MongoDB ObjectIDs are 12-byte binary values, represented as 24-character hex strings.
        if (! is_string($value) || ! preg_match('/^[a-f\d]{24}$/i', $value)) {
            $fail("Le champ {$attribute} doit être un identifiant MongoDB valide.");
        }
    }
}
