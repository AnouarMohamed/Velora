<?php

namespace App\Http\Requests\Events;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for updating an existing event.
 *
 * This request handles partial updates to event details. All fields are optional
 * but must follow the specified rules if provided.
 */
class UpdateEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Authenticated users can attempt this; specific permissions
     * (e.g., event owner or admin) are checked in the business logic.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Rules use 'sometimes' to allow partial updates:
     * - title: String, max 255.
     * - start_at: Valid date.
     * - end_at: Valid date after start_at.
     * - capacity: Integer >= 1.
     * - status: Must be a valid event status (draft, published, completed, etc.).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'room' => ['nullable', 'string', 'max:255'],
            'start_at' => ['sometimes', 'date'],
            'end_at' => ['sometimes', 'date', 'after:start_at'],
            'capacity' => ['sometimes', 'integer', 'min:1'],
            'ticket_price' => ['nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in([
                Event::STATUS_DRAFT,
                Event::STATUS_PUBLISHED,
                Event::STATUS_COMPLETED,
                Event::STATUS_CANCELLED,
                Event::STATUS_PENDING_PUBLICATION,
            ])],
        ];
    }
}
