<?php

namespace App\Http\Requests\Events;

use App\Rules\MongoObjectId;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for assigning an organizer to an event.
 *
 * This request handles the validation and authorization for updating
 * the primary organizer responsible for a specific event.
 */
class AssignEventOrganizerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Only users with administrative privileges are allowed to reassign event organizers.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Rules:
     * - organizer_id: Required, must be a valid MongoDB ObjectId string.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'organizer_id' => ['required', 'string', new MongoObjectId],
        ];
    }
}
