<?php

namespace App\Http\Requests\Feedbacks;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for submitting feedback for an event.
 *
 * This request ensures that only participants can submit feedback
 * and validates that the rating and comments meet specific standards.
 */
class StoreFeedbackRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Only users with the 'PARTICIPANT' role are allowed to submit feedback.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->getAttribute('role') === User::ROLE_PARTICIPANT;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Rules:
     * - rating: Required integer between 1 and 5.
     * - comment: Optional string, maximum 2000 characters.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
