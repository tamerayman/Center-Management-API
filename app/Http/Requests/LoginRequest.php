<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ];
    }
    public function messages():array{
    return [

            'email.required' => 'the email is required',
            'email.email' => 'the email must be a valid email address',
            'email.string' => 'the email should be string',

            'password.required' => 'the password is required',
            'password.string' => 'the password should be string',

        ];
    }
}
