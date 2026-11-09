<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCourseFeeRequest extends FormRequest
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
            'monthly_fee' => ['required', 'numeric', 'min:1', 'max:999999.99', 'decimal:0,2'],
            'effective_from' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'monthly_fee.required' => 'The monthly fee is required.',
            'monthly_fee.min' => 'The monthly fee must be at least 1 EGP.',
            'monthly_fee.decimal' => 'The monthly fee may have at most 2 decimal places.',
            'effective_from.after_or_equal' => 'The effective date cannot be in the past.',
        ];
    }
}
