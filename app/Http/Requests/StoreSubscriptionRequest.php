<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionRequest extends FormRequest
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
            'student_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('role', 'student'),
            ],
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'start_date' => ['nullable', 'date', 'before_or_equal:today'],
            'monthly_fee' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'monthly_discount' => ['nullable', 'numeric', 'min:0'],
            'discount_reason' => ['nullable', 'string', 'max:255', 'required_with:monthly_discount'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.exists' => 'The student_id must belong to an existing student.',
            'discount_reason.required_with' => 'A discount reason is required when a discount is applied.',
            'start_date.before_or_equal' => 'The subscription start date cannot be in the future.',
        ];
    }
}
