<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UnzipRemoteArchiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'archive_path' => [
                'required',
                'string',
                'max:4096',
                'regex:#^/(?!.*(?:^|/)\.\.(?:/|$))[^\x00\r\n]+\.zip$#i',
            ],
            'destination_path' => [
                'required',
                'string',
                'max:4096',
                'regex:#^/(?!.*(?:^|/)\.\.(?:/|$))[^\x00\r\n]+$#',
            ],
            'delete_archive' => ['sometimes', 'boolean'],
            'delete_existing_index' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'archive_path.regex' => 'The archive path must be an absolute remote path ending in .zip without parent-directory segments.',
            'destination_path.regex' => 'The destination path must be an absolute remote path without parent-directory segments.',
        ];
    }
}
