<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'lesson_date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'location' => ['nullable', 'string', 'max:255'],
            'delivery_type' => ['nullable', 'in:onsite,online'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'The title is required',
            'title.string' => 'The title must be a string',
            'title.max' => 'The title may not be greater than 255 characters',
            'description.string' => 'The description must be a string',
            'lesson_date.required' => 'The lesson date is required',
            'lesson_date.date' => 'The lesson date must be a valid date',
            'start_time.date_format' => 'The start time must be in H:i format (e.g. 14:30).',
            'end_time.date_format' => 'The end time must be in H:i format (e.g. 16:00).',
            'end_time.after' => 'The end time must be after the start time.',
            'delivery_type.in' => 'The delivery type must be onsite or online.',
        ];
    }
}
