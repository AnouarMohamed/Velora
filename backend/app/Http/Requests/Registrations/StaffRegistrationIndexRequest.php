<?php

namespace App\Http\Requests\Registrations;

use App\Rules\MongoObjectId;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for listing and filtering registrations by staff members.
 *
 * This request provides filtering capabilities for staff to view attendee registrations,
 * allowing for specific event lookup, payment status filtering, and keyword search.
 */
class StaffRegistrationIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Authenticated users (typically staff/admin) can make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Rules:
     * - event_id: Optional, must be a valid MongoDB ObjectId.
     * - payment_status: Optional, one of 'pending', 'paid', or 'all'.
     * - q: Optional search query string, max 120 chars.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event_id' => ['nullable', 'string', new MongoObjectId],
            'payment_status' => ['nullable', 'in:pending,paid,all'],
            'q' => ['nullable', 'string', 'max:120'],
        ];
    }
}
