<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentRequest extends FormRequest
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
            'amount' => ['sometimes', 'numeric', 'min:0.01', 'max:999999.99', 'decimal:0,2'],
            'paid_at' => ['sometimes', 'date', 'before_or_equal:today'],
            'method' => ['sometimes', 'in:cash,vodafone_cash,bank_transfer,card,other'],
            'note' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'The payment amount must be greater than zero.',
            'amount.decimal' => 'The payment amount may have at most 2 decimal places.',
        ];
    }
}
