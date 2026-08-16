<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLessonMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'title' => [
                $this->isMethod('post') && !$this->has('_method') ? 'required' : 'nullable',
                'string',
                'max:255',
            ],

            // Upload File
            'file' => [
                'nullable',
                'file',
                'mimes:pdf,doc,docx,ppt,pptx,zip,jpg,jpeg,png',
                'max:102400', // 100 MB
            ],

            // External Link (YouTube - Google Drive - etc...)
            'external_url' => [
                'nullable',
                'url',
            ],

        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {

            $hasFile = $this->hasFile('file');
            $hasUrl  = $this->filled('external_url');

            // لازم يرفع ملف أو يحط لينك
            if (!$hasFile && !$hasUrl) {

                $validator->errors()->add(
                    'file',
                    'Please upload a file or provide an external URL.'
                );
            }

            // مينفعش الاتنين مع بعض
            if ($hasFile && $hasUrl) {

                $validator->errors()->add(
                    'file',
                    'Please upload a file OR provide an external URL, not both.'
                );
            }

        });
    }

    public function messages(): array
    {
       return [
        // Title messages
        'title.required' => 'The title field is required.',
        'title.string' => 'The title must be a string.',
        'title.max' => 'The title may not be longer than 255 characters.',

        // File upload messages
        'file.file' => 'The uploaded file is invalid.',
        'file.mimes' => 'Allowed file types: pdf, doc, docx, ppt, pptx, zip, jpg, jpeg, png.',
        'file.max' => 'The file size must not exceed 100 MB.',

       // Custom validation messages (from withValidator)
        'file.required_without' => 'Please upload a file or provide an external URL.',
    ];

    }
}