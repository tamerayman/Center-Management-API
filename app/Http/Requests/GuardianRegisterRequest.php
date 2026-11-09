<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GuardianRegisterRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'unique:guardians,phone'],
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:guardians,email'],
            'password' => ['required', 'string', 'min:8'],
            // الكود بيتحقق منه في الـ controller برسالة واضحة لو الطالب مش موجود
            'guardian_code' => ['required', 'string', 'max:20'],
            'relationship' => ['required', 'in:father,mother,guardian'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'The phone number is required.',
            'phone.unique' => 'This phone number is already registered.',
            'email.unique' => 'This email is already registered.',
            'password.min' => 'The password must be at least 8 characters.',
            'guardian_code.required' => 'The guardian code is required.',
            'relationship.required' => 'The relationship is required.',
            'relationship.in' => 'The relationship must be father, mother or guardian.',
        ];
    }
}
