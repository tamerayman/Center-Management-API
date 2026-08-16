<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreExamRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
             'title' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'start_time' => ['required', 'date'],
                'end_time' => ['required', 'date', 'after:start_time'],
                'duration' => ['required', 'integer', 'min:10'],
                'total_marks' => ['required', 'integer', 'min:10'],    

        ];
    }
    public function messages(): array
    {
        return [
            'title.required' => 'The title field is required.',
            'title.string' => 'The title must be a string.',
            'title.max' => 'The title may not be greater than 255 characters.',
            'description.string' => 'The description must be a string.',
            'start_time.required' => 'The start time field is required.',
            'start_time.date' => 'The start time must be a valid date.',
            'end_time.required' => 'The end time field is required.',
            'end_time.date' => 'The end time must be a valid date.',
            'end_time.after' => 'The end time must be after the start time.',
            'duration.required' => 'The duration field is required.',
            'duration.integer' => 'The duration must be an integer.',
            'duration.min' => 'The duration must be at least 10 minutes.',
            'total_marks.required' => 'The total marks field is required.',
            'total_marks.integer' => 'The total marks must be an integer.',
            'total_marks.min' => 'The total marks must be at least 10.',
        ];
    }
}
