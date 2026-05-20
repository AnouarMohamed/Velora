<?php

namespace App\Http\Requests\EventActivities;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for updating an existing event activity.
 *
 * Allows for partial updates of activity details including title, timing, and sequence.
 */
class UpdateEventActivityRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Rules:
     * - title: Optional string (if provided), max 255 chars.
     * - description: Optional string.
     * - starts_at/ends_at: Optional dates, ends_at must be after starts_at.
     * - sort_order: Optional integer for display sequencing.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
