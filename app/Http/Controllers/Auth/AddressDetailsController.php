<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PhoneVerificationService;
use App\Support\CountryOptions;
use App\Support\LanguageOptions;
use App\Support\PhoneDialOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AddressDetailsController extends Controller
{
    public function __construct(
        protected PhoneVerificationService $phoneVerification
    ) {}

    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->phone_verified_at) {
            return redirect()->route('onboarding.index');
        }

        return Inertia::render('Auth/AddressDetails', [
            'phone' => $user->phone ?? '',
            'address' => $user->address ?? '',
            'city' => $user->city ?? '',
            'country' => $user->country ?? 'US',
            'postal_code' => $user->postal_code ?? '',
            'preferred_language' => $user->preferred_language ?? 'en',
            'countryOptions' => CountryOptions::selectOptions(),
            'languageOptions' => LanguageOptions::selectOptions(),
            'phoneDialOptions' => PhoneDialOptions::selectOptions(),
        ]);
    }

    public function sendOtp(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->phone_verified_at) {
            return redirect()->route('onboarding.index');
        }

        $validated = $request->validate([
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'required', 'string', 'max:120'],
            'country' => ['sometimes', 'required', 'string', Rule::in(CountryOptions::codes())],
            'postal_code' => ['sometimes', 'required', 'string', 'max:32'],
            'preferred_language' => ['sometimes', 'required', 'string', Rule::in(array_keys(LanguageOptions::labels()))],
            'phone' => ['sometimes', 'required', 'string', 'min:10', 'max:32'],
        ]);

        $address = $validated['address'] ?? $user->address;
        $city = $validated['city'] ?? $user->city;
        $country = $validated['country'] ?? $user->country;
        $postalCode = $validated['postal_code'] ?? $user->postal_code;
        $preferred = $validated['preferred_language'] ?? $user->preferred_language;

        if (! $city || ! $country || ! $postalCode || ! $preferred) {
            return back()->withErrors(['address' => 'Please complete your address details first.']);
        }

        $user->update([
            'address' => $address,
            'city' => $city,
            'country' => $country,
            'postal_code' => $postalCode,
            'preferred_language' => $preferred,
            'languages' => [$preferred],
        ]);

        $phone = $validated['phone'] ?? null;
        if (! $phone) {
            return redirect()->route('address-detail.otp');
        }

        $this->phoneVerification->sendOtp($user, $phone);

        return back()->with('otp_sent', true);
    }

    public function showOtp(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->phone_verified_at) {
            return redirect()->route('onboarding.index');
        }

        return Inertia::render('Auth/VerifyOtp', [
            'phone' => $user->phone ?? '',
            'phoneDialOptions' => PhoneDialOptions::selectOptions(),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->phone_verified_at) {
            return redirect()->route('onboarding.index');
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ]);

        if (! $this->phoneVerification->verify($user, $validated['code'])) {
            return back()->withErrors(['code' => 'Invalid or expired code. Try again or request a new code.']);
        }

        return redirect()->route('onboarding.index')->with('success', 'Phone number verified.');
    }
}
