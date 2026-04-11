<?php

namespace App\Http\Requests\Affiliates;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAffiliateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAffiliate() ?? false;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'social_profile_url' => ['nullable', 'url', 'max:255'],
            'payout_method' => ['nullable', 'string', 'max:100'],
            'paypal_email' => ['nullable', 'email', 'max:255'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
