<?php

namespace App\Http\Requests\Affiliates;

use App\Enums\AffiliateCommissionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAffiliateInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'commission_type_override' => ['nullable', Rule::in(AffiliateCommissionType::values())],
            'commission_value_override' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
