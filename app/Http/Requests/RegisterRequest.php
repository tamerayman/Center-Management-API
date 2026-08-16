<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
            'name'=>'required|string|max:255',
            'email'=>'required|string|email|max:255|unique:users,email',
            'password'=>'required|string|min:8|confirmed',
            'role'=>'required|in:student,teacher'
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'the name is required',
            'name.string' => 'the name should be string',
            'name.max' => 'the name should be smaller than 255',

            'email.required' => 'the email is required',
            'email.email' => 'the email must be a valid email address',
            'email.string' => 'the email should be string',
            'email.max' => 'the email should be smaller than 255',
            'email.unique' => 'the email has already been taken',

            'password.required' => 'the password is required',
            'password.string' => 'the password should be string',
            'password.min' => 'the password must be at least 8 characters',

            'role.required' => 'the role is required',
            'role.in' => 'the role must be either student or teacher',
        ];
    }
}
