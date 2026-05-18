<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CommunityImportUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $fileRules = [
            'required',
            'file',
            'mimes:csv,txt',
            'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel',
            'max:20480',
        ];

        return [
            'selected_owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'users_file' => $fileRules,
            'posts_file' => $fileRules,
            'comments_file' => $fileRules,
            'reactions_file' => $fileRules,
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $selectedOwnerId = $this->integer('selected_owner_id');

            if (! $selectedOwnerId) {
                return;
            }

            $selectedOwner = User::query()->find($selectedOwnerId);

            if (! $selectedOwner || ! $selectedOwner->hasAnyRole(['admin', 'super_admin'])) {
                $validator->errors()->add('selected_owner_id', 'The selected owner must be an admin or super admin.');
            }
        });
    }
}
