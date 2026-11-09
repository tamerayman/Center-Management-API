<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CancelPaymentRequest extends FormRequest
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
            'cancellation_reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'cancellation_reason.required' => 'A cancellation reason is required.',
        ];
    }
}
