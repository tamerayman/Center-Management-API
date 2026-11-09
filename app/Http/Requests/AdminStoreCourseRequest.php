<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminStoreCourseRequest extends FormRequest
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
            'teacher_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('role', 'teacher'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'The title field is required.',
            'teacher_id.required' => 'The teacher_id field is required.',
            'teacher_id.exists' => 'The teacher_id must belong to an existing teacher.',
        ];
    }
}
