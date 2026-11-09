<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSubscriptionRequest extends FormRequest
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
            'status' => ['sometimes', 'in:active,paused,cancelled,completed'],
            'end_date' => ['nullable', 'date'],
            'monthly_fee' => ['sometimes', 'numeric', 'min:0', 'max:999999.99'],
            'monthly_discount' => ['sometimes', 'numeric', 'min:0'],
            'discount_reason' => ['nullable', 'string', 'max:255'],
            'cancellation_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * الخصم مينفعش يعدي السعر الشهري.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $subscription = $this->route('subscription');
            if (! $subscription) {
                return;
            }

            $fee = $this->input('monthly_fee', $subscription->monthly_fee);
            $discount = $this->input('monthly_discount', $subscription->monthly_discount);

            if ((float) $discount > (float) $fee) {
                $validator->errors()->add(
                    'monthly_discount',
                    'The discount cannot exceed the monthly fee.'
                );
            }
        });
    }
}
