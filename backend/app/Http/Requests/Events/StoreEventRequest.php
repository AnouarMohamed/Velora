<?php

namespace App\Http\Requests\Events;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for creating a new event.
 *
 * This request handles the initial creation of an event, including its
 * basic information, scheduling, capacity, and optional branding images.
 */
class StoreEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Any authenticated user can attempt to create an event,
     * though specific role checks might be applied at the controller or service level.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Rules:
     * - title: Required string, max 255 chars.
     * - description: Optional string.
     * - location/room: Optional strings, max 255 chars.
     * - start_at: Required date.
     * - end_at: Required date, must be after start_at.
     * - capacity: Required positive integer.
     * - ticket_price: Optional numeric, min 0.
     * - status: Optional, must be a valid event status (draft, published, etc.).
     * - image_data: Optional base64 encoded image data.
     * - image_mime: Optional image MIME type (jpeg, png, webp, gif).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'room' => ['nullable', 'string', 'max:255'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'capacity' => ['required', 'integer', 'min:1'],
            'ticket_price' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in([
                Event::STATUS_DRAFT,
                Event::STATUS_PUBLISHED,
                Event::STATUS_CANCELLED,
                Event::STATUS_PENDING_PUBLICATION,
            ])],
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
