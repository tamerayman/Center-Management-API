<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GenerateChargesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // الحماية عبر gate: finance.sensitive
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'period_month' => ['required', 'integer', 'between:1,12'],
            'period_year' => ['required', 'integer', 'between:2020,2100'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
        ];
    }
}
