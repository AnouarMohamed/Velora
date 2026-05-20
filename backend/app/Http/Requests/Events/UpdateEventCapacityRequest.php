<?php

namespace App\Http\Requests\Events;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for updating only the capacity of an event.
 *
 * This request is used for surgical updates to an event's maximum capacity,
 * often used by organizers to scale attendance limits.
 */
class UpdateEventCapacityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Authenticated users can attempt this; ownership/role checks
     * are usually handled in the controller or service layer.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Rules:
     * - capacity: Required, must be an integer of at least 1.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'capacity' => ['required', 'integer', 'min:1'],
        ];
    }
}
