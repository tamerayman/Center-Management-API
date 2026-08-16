<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreQuestionRequest extends FormRequest
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
            
            'question_text' => ['required', 'string'],
            'option_a' => ['required', 'string'],
            'option_b' => ['required', 'string'],
            'option_c' => ['required', 'string'],
            'option_d' => ['required', 'string'],
            'correct_answer' => ['required', 'in:a,b,c,d'],
            'marks' => ['required', 'integer', 'min:1'],
            

        ];
    }
    public function messages(): array
    {
        return [
            'question_text.required' => 'The question text field is required.',
            'question_text.string' => 'The question text must be a string.',
            'option_a.required' => 'Option A is required.',
            'option_a.string' => 'Option A must be a string.',
            'option_b.required' => 'Option B is required.',
            'option_b.string' => 'Option B must be a string.',
            'option_c.required' => 'Option C is required.',
            'option_c.string' => 'Option C must be a string.',
            'option_d.required' => 'Option D is required.',
            'option_d.string' => 'Option D must be a string.',
            'correct_answer.required' => 'The correct answer field is required.',
            'correct_answer.in' => 'The correct answer must be one of the following: a, b, c, d.',
            'marks.required' => 'The marks field is required.',
            'marks.integer' => 'The marks must be an integer.',
            'marks.min' => 'The marks must be at least 1.',
        ];
    }
}
