<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Services\PhoneVerificationService;
use App\Support\CountryOptions;
use App\Support\LanguageOptions;
use App\Support\PhoneDialOptions;
use App\Support\UsStateOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        Log::channel('single')->info('AddressDetails show page opened.', [
            'user_id' => $user?->id,
            'route' => $request->path(),
            'ip' => $request->ip(),
        ]);

        if ($user->phone_verified_at) {
            return $this->redirectToNextStep($user);
        }

        $onboardingLocation = $user->onboarding_data['location'] ?? [];

        return Inertia::render('Auth/AddressDetails', [
            'phone' => $user->phone ?? '',
            'isProvider' => $user->followsProviderOnboarding(),
            'address' => $user->address ?? '',
            'city' => $user->city ?? '',
            'state' => $user->state ?? '',
            'country' => $user->country ?? 'US',
            'postal_code' => $user->postal_code ?? '',
            'county' => $onboardingLocation['county'] ?? '',
            'location_label' => $onboardingLocation['label'] ?? '',
            'preferred_language' => $user->preferred_language ?? 'en',
            'countryOptions' => CountryOptions::selectOptions(),
            'stateOptions' => UsStateOptions::selectOptions($user->country ?? 'US'),
            'languageOptions' => LanguageOptions::selectOptions(),
            'phoneDialOptions' => PhoneDialOptions::selectOptions(),
        ]);
    }

    public function autocomplete(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $query = trim($validated['query']);
        $country = null;
        $apiKey = (string) config('services.google.maps_api_key', env('GOOGLE_MAPS_API_KEY'));

        Log::channel('single')->info('AddressDetails autocomplete requested.', [
            'user_id' => $user?->id,
            'query' => $query,
            'country' => $country,
            'ip' => $request->ip(),
            'has_api_key' => filled($apiKey),
        ]);

        if (! filled($apiKey)) {
            Log::channel('single')->warning('AddressDetails autocomplete missing API key.');

            return response()->json([
                'ok' => false,
                'message' => 'Google API key is missing.',
            ], 500);
        }

        $callAutocomplete = static function (array $body) use ($apiKey) {
            return Http::timeout(10)
                ->withHeaders([
                    'X-Goog-Api-Key' => $apiKey,
                    'X-Goog-FieldMask' => 'suggestions.placePrediction.place,suggestions.placePrediction.text,suggestions.queryPrediction.text',
                ])
                ->post('https://places.googleapis.com/v1/places:autocomplete', $body);
        };

        $autocompleteBody = [
            'input' => $query,
            'includedPrimaryTypes' => ['street_address', 'premise', 'subpremise'],
        ];

        $autocompleteResponse = $callAutocomplete($autocompleteBody);

        if (! $autocompleteResponse->successful()) {
            Log::channel('single')->warning('AddressDetails autocomplete API call failed.', [
                'status' => $autocompleteResponse->status(),
                'body' => $autocompleteResponse->json(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Autocomplete request failed.',
            ], 502);
        }

        $suggestions = collect(data_get($autocompleteResponse->json(), 'suggestions', []));
        $placeResourceName = $suggestions
            ->map(fn ($item) => data_get($item, 'placePrediction.place'))
            ->first(fn ($value) => filled($value));

        // Fallback: retry without strict type/region filters when no place prediction is returned.
        if (! $placeResourceName) {
            $fallbackResponse = $callAutocomplete([
                'input' => $query,
            ]);

            if ($fallbackResponse->successful()) {
                $fallbackSuggestions = collect(data_get($fallbackResponse->json(), 'suggestions', []));
                $placeResourceName = $fallbackSuggestions
                    ->map(fn ($item) => data_get($item, 'placePrediction.place'))
                    ->first(fn ($value) => filled($value));
            } else {
                Log::channel('single')->warning('AddressDetails autocomplete fallback API call failed.', [
                    'status' => $fallbackResponse->status(),
                    'body' => $fallbackResponse->json(),
                ]);
            }
        }

        if (! $placeResourceName) {
            Log::channel('single')->info('AddressDetails autocomplete no place suggestions.', [
                'query' => $query,
                'country' => $country,
            ]);

            return response()->json([
                'ok' => true,
                'data' => null,
            ]);
        }

        $placeResponse = Http::timeout(10)
            ->withHeaders([
                'X-Goog-Api-Key' => $apiKey,
                'X-Goog-FieldMask' => 'formattedAddress,addressComponents',
            ])
            ->get("https://places.googleapis.com/v1/{$placeResourceName}");

        if (! $placeResponse->successful()) {
            Log::channel('single')->warning('AddressDetails place details call failed.', [
                'status' => $placeResponse->status(),
                'body' => $placeResponse->json(),
                'place' => $placeResourceName,
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Place details request failed.',
            ], 502);
        }

        $place = $placeResponse->json();
        Log::channel('single')->info('AddressDetails autocomplete resolved place.', [
            'user_id' => $user?->id,
            'query' => $query,
            'place' => $placeResourceName,
            'formatted' => data_get($place, 'formattedAddress'),
        ]);

        return response()->json([
            'ok' => true,
            'data' => $place,
        ]);
    }

    public function sendOtp(Request $request): RedirectResponse
    {
        $user = $request->user();

        Log::channel('single')->info('AddressDetails sendOtp request received.', [
            'user_id' => $user?->id,
            'route' => $request->path(),
            'ip' => $request->ip(),
            'address_payload' => [
                'address' => $request->input('address'),
                'city' => $request->input('city'),
                'state' => $request->input('state'),
                'country' => $request->input('country'),
                'postal_code' => $request->input('postal_code'),
                'preferred_language' => $request->input('preferred_language'),
                'serve_client_in_location' => $request->boolean('serve_client_in_location'),
            ],
            'has_phone' => filled($request->input('phone')),
        ]);

        if ($user->phone_verified_at) {
            Log::channel('single')->info('AddressDetails sendOtp skipped because phone is already verified.', [
                'user_id' => $user->id,
            ]);

            return $this->redirectToNextStep($user);
        }

        $validated = $request->validate([
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'required', 'string', 'max:120'],
            'state' => ['sometimes', 'required', 'string', 'max:120'],
            'country' => ['sometimes', 'required', 'string', Rule::in(CountryOptions::codes())],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:32'],
            'county' => ['sometimes', 'nullable', 'string', 'max:120'],
            'location_label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'preferred_language' => ['sometimes', 'required', 'string', Rule::in(array_keys(LanguageOptions::labels()))],
            'phone' => ['sometimes', 'required', 'string', 'min:10', 'max:32'],
        ]);

        Log::channel('single')->info('AddressDetails sendOtp validated payload.', [
            'user_id' => $user->id,
            'validated' => $validated,
        ]);

        $address = $validated['address'] ?? $user->address;
        $city = $validated['city'] ?? $user->city;
        $state = $validated['state'] ?? $user->state;
        $country = $validated['country'] ?? $user->country;
        $postalCode = $validated['postal_code'] ?? $user->postal_code;
        $county = $validated['county'] ?? data_get($user->onboarding_data, 'location.county');
        $locationLabel = $validated['location_label'] ?? data_get($user->onboarding_data, 'location.label');
        $preferred = $validated['preferred_language'] ?? $user->preferred_language;

        $countryUpper = strtoupper((string) $country);

        if (! $city || ! $state || ! $country || ! $preferred) {
            Log::channel('single')->warning('AddressDetails sendOtp blocked due to incomplete address details.', [
                'user_id' => $user->id,
                'resolved_values' => [
                    'address' => $address,
                    'city' => $city,
                    'state' => $state,
                    'country' => $country,
                    'postal_code' => $postalCode,
                    'preferred_language' => $preferred,
                ],
            ]);

            return back()->withErrors(['address' => 'Please complete your address details first.']);
        }

        if ($countryUpper === 'US' && ! filled($postalCode)) {
            return back()->withErrors(['postal_code' => 'Please choose a ZIP or city for your state (United States).']);
        }

        $onboardingData = array_merge($user->onboarding_data ?? [], [
            'location' => array_merge($user->onboarding_data['location'] ?? [], [
                'city' => $city,
                'state' => $state,
                'postal_code' => $postalCode,
                'country' => $country,
                'county' => $county,
                'label' => $locationLabel,
            ]),
        ]);

        $user->update([
            'address' => $address,
            'city' => $city,
            'state' => $state,
            'country' => $country,
            'postal_code' => $postalCode ?: null,
            'preferred_language' => $preferred,
            'languages' => [$preferred],
            'onboarding_data' => $onboardingData,
        ]);

        Log::channel('single')->info('AddressDetails sendOtp persisted user address details.', [
            'user_id' => $user->id,
            'saved' => [
                'address' => $address,
                'city' => $city,
                'state' => $state,
                'country' => $country,
                'postal_code' => $postalCode,
                'county' => $county,
                'location_label' => $locationLabel,
                'preferred_language' => $preferred,
            ],
        ]);

        $phone = $validated['phone'] ?? null;
        if (! $phone) {
            Log::channel('single')->info('AddressDetails sendOtp completed without phone send; redirecting to OTP step.', [
                'user_id' => $user->id,
            ]);

            return redirect()->route('address-detail.otp');
        }

        $this->phoneVerification->sendOtp($user, $phone);

        Log::channel('single')->info('AddressDetails sendOtp OTP sent successfully.', [
            'user_id' => $user->id,
            'phone_last4' => substr((string) $phone, -4),
        ]);

        return back()->with('otp_sent', true);
    }

    public function showOtp(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->phone_verified_at) {
            return $this->redirectToNextStep($user);
        }

        return Inertia::render('Auth/VerifyOtp', [
            'phone' => $user->phone ?? '',
            'isProvider' => $user->followsProviderOnboarding(),
            'phoneDialOptions' => PhoneDialOptions::selectOptions(),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->phone_verified_at) {
            return $this->redirectToNextStep($user);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ]);

        if (! $this->phoneVerification->verify($user, $validated['code'])) {
            return back()->withErrors(['code' => 'Invalid or expired code. Try again or request a new code.']);
        }

        return $this->redirectToNextStep($user)->with('success', 'Phone number verified.');
    }

    protected function redirectToNextStep($user): RedirectResponse
    {
        if ($user->followsProviderOnboarding()) {
            if (! $user->isProvider()) {
                $user->assignRole(UserRole::PROVIDER->value);
            }

            return redirect()->route('onboarding.index', ['step' => 4]);
        }

        return redirect()->route('onboarding.index', ['step' => 2]);
    }
}
