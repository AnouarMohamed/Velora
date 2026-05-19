<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class MongoObjectId implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^[a-f\d]{24}$/i', $value)) {
            $fail("Le champ {$attribute} doit être un identifiant MongoDB valide.");
        }
    }
}
