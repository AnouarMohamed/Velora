<?php

namespace App\Http\Requests\Users;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Form request for administrators to create a new user manually.
 *
 * Unlike registration, this allows assigning any system role (Admin, Organizer, etc.).
 */
class StoreUserRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Rules:
     * - name: Required string, max 255.
     * - email: Required email format.
     * - password: Required string, follows security defaults.
     * - role: Required, must be a valid system role.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'password' => ['required', Password::defaults()],
            'role' => ['required', Rule::in([
                User::ROLE_ADMIN,
                User::ROLE_ORGANIZER,
                User::ROLE_PARTICIPANT,
                User::ROLE_CLIENT,
            ])],
        ];
    }
}
