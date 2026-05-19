<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::in([
                User::ROLE_PARTICIPANT,
                User::ROLE_CLIENT,
            ])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.email' => 'L\'adresse e-mail n\'est pas valide.',
        ];
    }
}
