<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreS3UploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:'.max(1, (int) config('uploads.s3.max_size_kb', 10240)),
                'mimes:'.implode(',', $this->allowedMimes()),
            ],
            'directory' => ['nullable', 'string', 'max:120', 'regex:/^[a-zA-Z0-9\-\/_]+$/'],
        ];
    }

    private function allowedMimes(): array
    {
        $configured = config('uploads.s3.allowed_mimes', []);

        if (! is_array($configured) || $configured === []) {
            return ['jpg', 'jpeg', 'png', 'pdf'];
        }

        return array_values(array_filter(array_map(
            static fn ($extension) => is_string($extension) ? trim($extension) : '',
            $configured
        )));
    }
}
