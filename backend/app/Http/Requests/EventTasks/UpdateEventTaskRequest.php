<?php

namespace App\Http\Requests\EventTasks;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for updating an existing event task.
 *
 * Used to modify task details or mark them as completed.
 */
class UpdateEventTaskRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Rules:
     * - title: Optional string, max 255 chars.
     * - description: Optional string.
     * - is_done: Optional boolean to toggle task completion status.
     * - due_at: Optional date for deadline updates.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_done' => ['sometimes', 'boolean'],
            'due_at' => ['nullable', 'date'],
        ];
    }
}
