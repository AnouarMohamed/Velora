<?php

namespace App\Http\Requests\EventActivities;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for creating a new event activity (agenda item).
 *
 * Activities represent specific time-slots or sessions within a larger event.
 */
class StoreEventActivityRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Rules:
     * - title: Required string, max 255 chars.
     * - description: Optional string.
     * - starts_at: Optional date.
     * - ends_at: Optional date, must be equal to or after starts_at if provided.
     * - sort_order: Optional integer, used for displaying activities in a specific sequence.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
