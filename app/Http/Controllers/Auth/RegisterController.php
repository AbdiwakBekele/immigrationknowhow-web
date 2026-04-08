<?php

namespace App\Http\Controllers\Auth;

use App\Enums\ServiceType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\CountryOptions;
use App\Support\LanguageOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    private const CAPTCHA_SESSION_KEY = 'register_captcha';

    public function create(Request $request): Response
    {
        $captcha = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $request->session()->put(self::CAPTCHA_SESSION_KEY, $captcha);

        return Inertia::render('Auth/Register', [
            'roles' => [
                ['value' => UserRole::USER->value, 'label' => UserRole::USER->label(), 'description' => UserRole::USER->description()],
                ['value' => UserRole::PROVIDER->value, 'label' => UserRole::PROVIDER->label(), 'description' => UserRole::PROVIDER->description()],
            ],
            'serviceTypes' => ServiceType::options(),
            'languageOptions' => LanguageOptions::selectOptions(),
            'countryOptions' => CountryOptions::selectOptions(),
            'captchaCode' => $captcha,
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
            'terms' => ['required', 'accepted'],
            'service_type' => ['required', new Enum(ServiceType::class)],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'country' => ['required', 'string', Rule::in(CountryOptions::codes())],
            'postal_code' => ['required', 'string', 'max:32'],
            'preferred_language' => ['required', 'string', Rule::in(array_keys(LanguageOptions::labels()))],
            'captcha' => [
                'required',
                'string',
                'regex:/^\d{4}$/',
                function (string $attribute, mixed $value, \Closure $fail) use ($request) {
                    if ($value !== $request->session()->get(self::CAPTCHA_SESSION_KEY)) {
                        $fail('The captcha code is incorrect.');
                    }
                },
            ],
        ]);

        $preferred = $validated['preferred_language'];

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'address' => $validated['address'],
            'city' => $validated['city'],
            'country' => $validated['country'],
            'postal_code' => $validated['postal_code'],
            'preferred_language' => $preferred,
            'languages' => [$preferred],
            'onboarding_data' => [
                'registration' => [
                    'service_type' => $validated['service_type'],
                ],
            ],
        ]);

        $user->assignRole($validated['role']);

        $request->session()->forget(self::CAPTCHA_SESSION_KEY);

        Auth::login($user);

        return redirect()->route('verify-phone');
    }
}
