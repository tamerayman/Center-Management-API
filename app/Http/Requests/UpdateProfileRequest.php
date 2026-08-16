<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
    public function rules(): array
    {
        return [
            'phone' => ['nullable', 'string', 'max:20'],

            'bio' => ['nullable', 'string', 'max:500'],

            'date_of_birth' => ['nullable', 'date'],

            'gender' => ['nullable', 'in:male,female'],

            'country' => ['nullable', 'string', 'max:100'],

            'city' => ['nullable', 'string', 'max:100'],

            'github_url' => ['nullable', 'url'],

            'linkedin_url' => ['nullable', 'url'],

            'website_url' => ['nullable', 'url'],

            'avatar' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
        ];
    }
}
