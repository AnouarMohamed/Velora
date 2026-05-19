<?php

namespace App\Http\Requests\Registrations;

use App\Rules\MongoObjectId;
use Illuminate\Foundation\Http\FormRequest;

class StaffRegistrationIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'event_id' => ['nullable', 'string', new MongoObjectId],
            'payment_status' => ['nullable', 'in:pending,paid,all'],
            'q' => ['nullable', 'string', 'max:120'],
        ];
    }
}
