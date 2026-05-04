<?php

namespace App\Http\Requests\Mobile;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\ServiceTypeOptions;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends BaseMobileRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $role = (string) $this->input('role', UserRole::USER->value);
        $effectiveRole = in_array($role, [UserRole::USER->value, UserRole::PROVIDER->value, UserRole::ADVERTISER->value], true)
            ? $role
            : UserRole::USER->value;

        // Web dashboard forces provider when service_type is present. Mirror that for mobile.
        if (filled($this->input('service_type'))) {
            $effectiveRole = UserRole::PROVIDER->value;
        }

        $providerTypes = ServiceTypeOptions::values('provider');

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['nullable', 'string', 'in:'.UserRole::USER->value.','.UserRole::PROVIDER->value.','.UserRole::ADVERTISER->value],
            'service_type' => [
                Rule::requiredIf($effectiveRole === UserRole::PROVIDER->value),
                'nullable',
                'string',
                Rule::in($providerTypes),
            ],
        ];
    }

    public function effectiveRole(): string
    {
        $role = (string) $this->input('role', UserRole::USER->value);
        $effectiveRole = in_array($role, [UserRole::USER->value, UserRole::PROVIDER->value, UserRole::ADVERTISER->value], true)
            ? $role
            : UserRole::USER->value;

        if (filled($this->input('service_type'))) {
            $effectiveRole = UserRole::PROVIDER->value;
        }

        return $effectiveRole;
    }
}
