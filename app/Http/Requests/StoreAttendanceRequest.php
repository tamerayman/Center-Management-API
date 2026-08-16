<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceRequest extends FormRequest
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
            'students' => ['required', 'array', 'min:1'],

            'students.*.student_id' => [
                'required',
                'exists:users,id'
            ],

            'students.*.status' => [
                'required',
                'in:present,absent,late'
            ],
        ];
    }
}
