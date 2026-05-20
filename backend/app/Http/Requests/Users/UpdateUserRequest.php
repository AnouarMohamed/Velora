<?php

namespace App\Http\Requests\Users;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Form request for updating an existing user's information.
 *
 * Supports partial updates of name, email, password, and role.
 */
class UpdateUserRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Rules use 'sometimes' to allow partial updates:
     * - email: Valid email if provided.
     * - password: Optional, must meet security defaults if provided.
     * - role: Optional, must be a valid system role if provided.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email'],
            'password' => ['nullable', Password::defaults()],
            'role' => ['sometimes', Rule::in([
                User::ROLE_ADMIN,
                User::ROLE_ORGANIZER,
                User::ROLE_PARTICIPANT,
                User::ROLE_CLIENT,
            ])],
        ];
    }
}
