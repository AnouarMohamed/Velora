<?php

namespace App\Http\Requests\EventTasks;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for creating a new task associated with an event.
 *
 * Tasks are internal to-do items for organizers and staff to manage event planning.
 */
class StoreEventTaskRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Rules:
     * - title: Required string, max 255 chars.
     * - description: Optional string.
     * - due_at: Optional date for task completion deadline.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_at' => ['nullable', 'date'],
        ];
    }
}
