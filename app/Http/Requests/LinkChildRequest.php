<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LinkChildRequest extends FormRequest
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
            'guardian_code' => ['required', 'string', 'max:20'],
            'relationship' => ['required', 'in:father,mother,guardian'],
        ];
    }

    public function messages(): array
    {
        return [
            'guardian_code.required' => 'The guardian code is required.',
            'relationship.required' => 'The relationship is required.',
            'relationship.in' => 'The relationship must be father, mother or guardian.',
        ];
    }
}
