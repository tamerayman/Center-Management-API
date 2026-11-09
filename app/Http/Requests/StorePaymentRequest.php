<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // الحماية عبر gate: finance.recordPayment (على الكورس)
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
            'period_month' => ['required', 'integer', 'between:1,12'],
            'period_year' => ['required', 'integer', 'between:2020,2100'],
            // المبلغ الكامل الذي دفعه الطالب فعلياً — لو أكبر من المتبقي الزيادة تبقى credit
            'amount' => ['required', 'numeric', 'min:0', 'max:999999.99', 'decimal:0,2'], // 0 allowed when use_credit=true, enforced in PaymentService
            'paid_at' => ['nullable', 'date', 'before_or_equal:today'],
            'method' => ['required', 'in:cash,vodafone_cash,bank_transfer,card,other'],
            'note' => ['nullable', 'string', 'max:255'],
            'use_credit' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.exists' => 'The student_id must belong to an existing student.',
            'amount.min' => 'The payment amount cannot be negative.',
            'amount.decimal' => 'The payment amount may have at most 2 decimal places.',
            'method.in' => 'The payment method must be cash, vodafone_cash, bank_transfer, card or other.',
            'paid_at.before_or_equal' => 'The payment date cannot be in the future.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $amount = (float) $this->input('amount', 0);
            $useCredit = filter_var($this->input('use_credit', false), FILTER_VALIDATE_BOOLEAN);

            if ($amount <= 0 && ! $useCredit) {
                $validator->errors()->add('amount', 'The payment amount must be greater than zero when not using credit.');
            }
        });
    }
}
