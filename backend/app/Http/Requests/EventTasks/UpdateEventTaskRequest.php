<?php

namespace App\Http\Requests\EventTasks;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventTaskRequest extends FormRequest
{
    /** @return array<string, list<string>> */
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
