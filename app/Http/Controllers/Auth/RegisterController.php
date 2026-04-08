<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ServiceTypeOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/Register', [
            'roles' => [
                ['value' => UserRole::USER->value, 'label' => UserRole::USER->label(), 'description' => UserRole::USER->description()],
                ['value' => UserRole::PROVIDER->value, 'label' => UserRole::PROVIDER->label(), 'description' => UserRole::PROVIDER->description()],
            ],
            'serviceTypes' => ServiceTypeOptions::selectOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['required', 'string', 'in:'.UserRole::USER->value.','.UserRole::PROVIDER->value],
            'service_type' => [
                Rule::requiredIf($request->input('role') === UserRole::PROVIDER->value),
                'nullable',
                Rule::in(ServiceTypeOptions::values()),
            ],
        ]);

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'onboarding_data' => [
                'registration' => [
                    'service_type' => $validated['service_type'] ?? null,
                ],
            ],
        ]);

        $user->assignRole($validated['role']);

        Auth::login($user);

        if ($validated['role'] === UserRole::USER->value) {
            return redirect()->route('onboarding.index');
        }

        return redirect()->route('address-detail');
    }
}
