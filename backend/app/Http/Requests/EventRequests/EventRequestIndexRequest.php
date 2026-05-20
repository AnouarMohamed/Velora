<?php

namespace App\Http\Requests\EventRequests;

use App\Models\EventRequest;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EventRequestIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->isAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in([
                EventRequest::STATUS_PENDING,
                EventRequest::STATUS_APPROVED,
                EventRequest::STATUS_REJECTED,
            ])],
        ];
    }
}
