<?php

namespace App\Http\Requests\EventRequests;

use App\Models\EventRequest;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewEventRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->isAdmin();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in([
                EventRequest::STATUS_APPROVED,
                EventRequest::STATUS_REJECTED,
            ])],
            'rejection_reason' => ['required_if:decision,'.EventRequest::STATUS_REJECTED, 'nullable', 'string'],
        ];
    }
}
