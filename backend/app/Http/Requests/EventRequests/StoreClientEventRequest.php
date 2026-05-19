<?php

namespace App\Http\Requests\EventRequests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientEventRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $user = $this->user();

        $this->merge([
            'preferred_start' => $this->input('preferred_start') ?: null,
            'preferred_end' => $this->input('preferred_end') ?: null,
            'contact_phone' => $this->input('contact_phone') ?: null,
            'contact_email' => $user instanceof User ? $user->getAttribute('email') : $this->input('contact_email'),
            'contact_name' => $this->input('contact_name') ?: ($user instanceof User ? $user->getAttribute('name') : null),
        ]);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->getAttribute('role') === User::ROLE_CLIENT;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'preferred_start' => ['nullable', 'date'],
            'preferred_end' => ['nullable', 'date', 'after_or_equal:preferred_start'],
            'location' => ['nullable', 'string', 'max:255'],
            'ticket_price' => ['required', 'numeric', 'min:0'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:2048'],
            'image_data' => ['nullable', 'string'],
            'image_mime' => ['nullable', 'string', Rule::in([
                'image/jpeg',
                'image/png',
                'image/webp',
                'image/gif',
            ])],
        ];
    }
}
