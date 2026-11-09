<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $lesson = $this->route('lesson');
        $courseId = $lesson?->course_id;

        return [
            'students'               => ['required', 'array', 'min:1'],
            'students.*.student_id'  => [
                'required',
                // يجب أن يكون مستخدماً موجوداً بدور student فقط
                Rule::exists('users', 'id')->where('role', 'student'),
                // يجب أن يكون مسجلاً في كورس الدرس هذا
                Rule::exists('course_student', 'student_id')
                    ->where('course_id', $courseId),
            ],
            'students.*.status'      => [
                'required',
                'in:present,absent,late',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'students.*.student_id.exists' => 'The student must be a valid enrolled student in this course.',
        ];
    }
}
