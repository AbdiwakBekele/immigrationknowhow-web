<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\UserResource;
use App\Models\ProviderSubscription;
use App\Models\ServiceProvider;
use App\Models\SubscriptionPlan;
use App\Services\PhoneVerificationService;
use App\Support\CountryOptions;
use App\Support\LanguageOptions;
use App\Support\PhoneDialOptions;
use App\Support\ServiceTypeOptions;
use App\Support\UserRoleAccounts;
use App\Support\UsStateOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Stripe;

class OnboardingController extends Controller
{
    public function __construct(
        protected PhoneVerificationService $phoneVerification
    ) {}

    public function meta(Request $request): JsonResponse
    {
        $user = $request->user();

        $needsPhone = ! $user->hasCompletedSignupPhoneStep() && ! $user->isAdmin() && ! $user->isAffiliate();
        $intent = strtolower((string) $request->input('intent', ''));
        $isAddProviderFlow = $intent === 'provider'
            && $user->hasRole(UserRole::USER->value)
            && ! $user->serviceProvider()->exists();
        $isProvider = $user->followsProviderOnboarding() || $isAddProviderFlow;
        $isAdvertiser = $user->isAdvertiser() && ! $isProvider;

        $requestedStepDefault = $isProvider ? 4 : 2;
        $requestedStep = (int) $request->integer('step', $requestedStepDefault);

        if ($isProvider) {
            if ($needsPhone) {
                $initialStep = $user->hasCompletedSignupAddressStep() ? 3 : 2;
            } else {
                $initialStep = max(4, min(7, $requestedStep));
            }
        } elseif ($isAdvertiser) {
            if ($needsPhone && ! $user->hasCompletedSignupAddressStep()) {
                $initialStep = 2;
            } elseif ($needsPhone) {
                $initialStep = 3;
            } else {
                $advStep = (int) $request->integer('step', 4);
                $initialStep = max(2, min(4, $advStep));
            }
        } else {
            // Service seeker — mirror web Onboarding/User routing.
            $initialStep = $needsPhone ? max(2, min(3, $requestedStep)) : 4;
        }

        $subscriptionPlans = $isProvider
            ? SubscriptionPlan::query()
                ->active()
                ->with('serviceTypeOption:id,value,label')
                ->orderByDesc('is_featured')
                ->orderBy('sort_order')
                ->get([
                    'id',
                    'uuid',
                    'name',
                    'description',
                    'price_cents',
                    'currency',
                    'billing_cycle',
                    'features',
                    'is_featured',
                    'stripe_price_id',
                    'service_type_option_id',
                ])
            : collect();

        $countryForStates = $request->input('country') ?? $user->country ?? 'US';

        return $this->success('OK', [
            'user' => (new UserResource($user))->resolve(),
            'initialStep' => $initialStep,
            'requiresPhoneVerification' => $needsPhone,
            'phoneVerification' => $needsPhone
                ? [
                    'phone' => $user->phone ?? '',
                    'phoneDialOptions' => PhoneDialOptions::selectOptions(),
                ]
                : null,
            'isProvider' => $isProvider,
            'isAdvertiser' => $isAdvertiser && ! $isProvider,
            'serviceTypes' => $isProvider
                ? ServiceTypeOptions::selectOptions('provider')
                : ($isAdvertiser ? [] : ServiceTypeOptions::selectOptions('user')),
            'countryOptions' => CountryOptions::selectOptions(),
            'stateOptions' => UsStateOptions::selectOptions($countryForStates),
            'languageOptions' => LanguageOptions::selectOptions(),
            'existingData' => $this->mergedExistingData($user),
            'steps' => match (true) {
                $isProvider => $this->providerSteps(),
                $isAdvertiser => $this->advertiserSteps(),
                default => $this->userSteps(),
            },
            'subscriptionPlans' => $subscriptionPlans,
            'stripeBillingReady' => StripeProviderSubscriptionCheckout::secretConfigured(),
        ]);
    }

    public function sendOtp(Request $request): JsonResponse
    {
        $user = $request->user();
        $isProviderFlow = $user->followsProviderOnboarding();

        if ($user->hasCompletedSignupPhoneStep() && ! ($isProviderFlow && $request->filled('coverage_country'))) {
            return $this->success('Already verified', [
                'nextStep' => 4,
            ]);
        }

        // Provider flow has two submissions:
        // - coverage/service area (no OTP)
        // - phone-only (send OTP)
        if ($isProviderFlow) {
            $isCoverageStep = $request->filled('coverage_country');
            if ($isCoverageStep) {
                $validated = $request->validate([
                    'coverage_country' => ['required', 'string', Rule::in(['usa', 'uk', 'europe', 'canada', 'other'])],
                    'coverage_state' => ['required', 'string', 'max:120'],
                    'coverage_postal_code' => [
                        'nullable',
                        'string',
                        'max:32',
                        Rule::requiredIf(fn () => $request->input('coverage_country') === 'usa'),
                    ],
                    'service_area' => ['sometimes', 'array'],
                    'service_area.remote' => ['sometimes', 'boolean'],
                    'service_area.in_person' => ['sometimes', 'boolean'],
                    'service_area.radius' => ['sometimes', 'nullable', 'numeric'],
                    'service_area.areas' => ['sometimes', 'array'],
                    'serve_client_in_location' => ['sometimes', 'boolean'],
                    'preferred_language' => ['required', 'string', Rule::in(array_keys(LanguageOptions::labels()))],
                ]);

                $areas = $request->input('service_area.areas', []);
                $serviceArea = [
                    'remote' => $request->boolean('service_area.remote'),
                    'in_person' => $request->boolean('service_area.in_person', true),
                    'radius' => $request->filled('service_area.radius') ? (float) $request->input('service_area.radius') : null,
                    'areas' => is_array($areas) ? $areas : [],
                    'serve_client_in_location' => $request->boolean('serve_client_in_location'),
                ];

                $onboardingData = array_merge($user->onboarding_data ?? [], [
                    'coverage_area' => [
                        'country' => $validated['coverage_country'],
                        'state' => $validated['coverage_state'],
                        'postal_code' => $validated['coverage_postal_code'] ?? null,
                    ],
                    'service-area' => array_merge($user->onboarding_data['service-area'] ?? [], $serviceArea),
                ]);

                $preferred = $validated['preferred_language'];
                $user->update([
                    'preferred_language' => $preferred,
                    'languages' => [$preferred],
                    'onboarding_data' => $onboardingData,
                ]);

                if (! $user->isProvider()) {
                    $user->assignRole(UserRole::PROVIDER->value);
                }

                return $this->success('Coverage saved', [
                    'nextStep' => $user->hasCompletedSignupPhoneStep() ? 4 : 3,
                ]);
            }

            $validated = $request->validate([
                'phone' => ['required', 'string', 'min:8', 'max:32'],
            ]);

            $coverage = $user->onboarding_data['coverage_area'] ?? [];
            if (empty($coverage['country']) || empty($coverage['state'])) {
                return $this->error('Please complete coverage area before adding your phone number.', [
                    'phone' => ['Please complete coverage area before adding your phone number.'],
                ], 422);
            }
            if (($coverage['country'] ?? null) === 'usa' && ! filled($coverage['postal_code'] ?? null)) {
                return $this->error('Please add a city or ZIP code for your coverage area (USA).', [
                    'phone' => ['Please add a city or ZIP code for your coverage area (USA).'],
                ], 422);
            }

            try {
                $this->phoneVerification->sendOtp($user, $validated['phone']);
            } catch (\Exception $e) {
                return $this->error($e->getMessage(), [
                    'phone' => [$e->getMessage()],
                ], 422);
            }

            return $this->success('OTP sent', [
                'nextStep' => 3,
            ]);
        }

        // Seeker / advertiser: save address & optional profile/services, then optionally send OTP.
        $userServiceTypeValues = ServiceTypeOptions::values('user');

        $validated = $request->validate([
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'required', 'string', 'max:120'],
            'state' => ['sometimes', 'required', 'string', 'max:120'],
            'country' => ['sometimes', 'required', 'string', Rule::in(CountryOptions::codes())],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:32'],
            'county' => ['sometimes', 'nullable', 'string', 'max:120'],
            'location_label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'preferred_language' => ['sometimes', 'required', 'string', Rule::in(array_keys(LanguageOptions::labels()))],
            'phone' => ['sometimes', 'required', 'string', 'min:8', 'max:32'],
            'services_needed' => ['sometimes', 'nullable', 'array', 'max:8'],
            'services_needed.*' => ['string', Rule::in($userServiceTypeValues)],
            'number_of_children' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:50'],
            'children_ages_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'dogs_count' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:50'],
        ]);

        $address = $validated['address'] ?? $user->address;
        $city = $validated['city'] ?? $user->city;
        $state = $validated['state'] ?? $user->state;
        $country = $validated['country'] ?? $user->country;
        $postalCode = $validated['postal_code'] ?? $user->postal_code;
        $county = $validated['county'] ?? data_get($user->onboarding_data, 'location.county');
        $locationLabel = $validated['location_label'] ?? data_get($user->onboarding_data, 'location.label');
        $preferred = $validated['preferred_language'] ?? $user->preferred_language;

        if (! $city || ! $state || ! $country || ! $preferred) {
            return $this->error('Please complete your address details first.', [
                'address' => ['Please complete your address details first.'],
            ], 422);
        }

        if (strtoupper((string) $country) === 'US' && ! filled($postalCode)) {
            return $this->error('Please choose a ZIP or city for your state (United States).', [
                'postal_code' => ['Please choose a ZIP or city for your state (United States).'],
            ], 422);
        }

        $profilePatch = [];
        if (array_key_exists('number_of_children', $validated)) {
            $profilePatch['number_of_children'] = $validated['number_of_children'];
            $profilePatch['has_children'] = (int) $validated['number_of_children'] > 0;
        }
        if (array_key_exists('children_ages_text', $validated)) {
            $profilePatch['children_ages_text'] = $validated['children_ages_text'];
        }
        if (array_key_exists('dogs_count', $validated)) {
            $profilePatch['dogs_count'] = $validated['dogs_count'];
        }

        $profileExtras = array_merge($user->onboarding_data['profile'] ?? [], $profilePatch);

        $onboardingData = array_merge($user->onboarding_data ?? [], [
            'location' => array_merge($user->onboarding_data['location'] ?? [], [
                'city' => $city,
                'state' => $state,
                'postal_code' => $postalCode,
                'country' => $country,
                'county' => $county,
                'label' => $locationLabel,
            ]),
            'profile' => $profileExtras,
        ]);

        if (array_key_exists('services_needed', $validated) && is_array($validated['services_needed'])) {
            $onboardingData['services'] = array_merge($user->onboarding_data['services'] ?? [], [
                'services_needed' => $validated['services_needed'],
            ]);
        }

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

        $phone = $validated['phone'] ?? null;
        if ($phone) {
            try {
                $this->phoneVerification->sendOtp($user, $phone);
            } catch (\Exception $e) {
                return $this->error($e->getMessage(), [
                    'phone' => [$e->getMessage()],
                ], 422);
            }
        }

        return $this->success($phone ? 'OTP sent' : 'Address saved', [
            'nextStep' => 3,
        ]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasCompletedSignupPhoneStep()) {
            return $this->success('Already verified', ['nextStep' => 4]);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ]);

        if (! $this->phoneVerification->verify($user, $validated['code'])) {
            return $this->error('Invalid or expired code. Try again or request a new code.', [
                'code' => ['Invalid or expired code. Try again or request a new code.'],
            ], 422);
        }

        return $this->success('Phone verified', ['nextStep' => 4]);
    }

    public function saveProgress(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'step' => ['required', 'string', 'max:64'],
            'data' => ['nullable', 'array'],
        ]);

        $step = $validated['step'];
        $data = $validated['data'] ?? [];

        $onboardingData = array_merge($user->onboarding_data ?? [], [
            $step => $data,
            'last_completed_step' => $step,
        ]);

        $user->update(['onboarding_data' => $onboardingData]);

        return $this->success('Progress saved', []);
    }

    public function complete(Request $request): JsonResponse
    {
        $user = $request->user();

        // Mirror the web controller’s user-services validation.
        $userServiceTypeValues = ServiceTypeOptions::values('user');
        $request->validate([
            'services_needed' => ['nullable', 'array', 'max:8'],
            'services_needed.*' => ['string', Rule::in($userServiceTypeValues)],
        ]);

        if (! $user->hasCompletedSignupPhoneStep() && ! $user->isAdmin() && ! $user->isAffiliate()) {
            return $this->error('Phone verification required', [], 403);
        }

        $isProvider = $user->followsProviderOnboarding();

        $providerForCheckout = null;
        $planForCheckout = null;

        try {
            DB::transaction(function () use ($user, $request, $isProvider, &$providerForCheckout, &$planForCheckout) {
                $data = $request->all();

                $onboardingData = array_merge($user->onboarding_data ?? [], [
                    'location' => array_merge($user->onboarding_data['location'] ?? [], [
                        'city' => $data['city'] ?? null,
                        'state' => $data['state'] ?? null,
                        'postal_code' => $data['postal_code'] ?? null,
                        'country' => $data['country'] ?? null,
                        'county' => $data['county'] ?? null,
                        'label' => $data['location_label'] ?? null,
                    ]),
                    'business' => array_merge($user->onboarding_data['business'] ?? [], $data['business'] ?? []),
                    'services' => array_merge(
                        $user->onboarding_data['services'] ?? [],
                        $data['services'] ?? [],
                        ['services_needed' => $data['services_needed'] ?? data_get($user->onboarding_data, 'services.services_needed', [])]
                    ),
                    'pricing' => array_merge($user->onboarding_data['pricing'] ?? [], $data['pricing'] ?? []),
                    'service-area' => array_merge($user->onboarding_data['service-area'] ?? [], $data['service-area'] ?? []),
                    'subscription' => array_merge($user->onboarding_data['subscription'] ?? [], $data['subscription'] ?? []),
                    'profile' => array_merge($user->onboarding_data['profile'] ?? [], $data['profile'] ?? []),
                ]);

                $lineOne = $data['address_line_1'] ?? $data['address'] ?? null;

                $user->update([
                    'address' => $lineOne ?? $user->address,
                    'city' => $data['city'] ?? $onboardingData['location']['city'] ?? $user->city,
                    'state' => $data['state'] ?? $onboardingData['location']['state'] ?? $user->state,
                    'country' => $data['country'] ?? $onboardingData['location']['country'] ?? $user->country ?? 'US',
                    'postal_code' => $data['postal_code'] ?? $onboardingData['location']['postal_code'] ?? $user->postal_code,
                    'languages' => $data['languages'] ?? $onboardingData['language']['languages'] ?? $user->languages ?? ['en'],
                    'preferred_language' => $data['preferred_language'] ?? $onboardingData['language']['preferred'] ?? $user->preferred_language ?? 'en',
                    'onboarding_completed' => true,
                    'onboarding_data' => $onboardingData,
                    'onboarding_completed_at' => now(),
                ]);

                if (! $isProvider) {
                    return;
                }

                if (! $user->isProvider()) {
                    $user->assignRole(UserRole::PROVIDER->value);
                }

                $businessData = $onboardingData['business'] ?? [];
                $servicesData = array_merge($onboardingData['services'] ?? [], $data['services'] ?? []);
                $pricingData = $onboardingData['pricing'] ?? [];
                $serviceAreaData = $onboardingData['service-area'] ?? [];
                $subscriptionData = $onboardingData['subscription'] ?? [];

                $serviceTypes = $servicesData['types'] ?? [];
                if ($serviceTypes === [] && ! empty($onboardingData['registration']['service_type'])) {
                    $serviceTypes = [$onboardingData['registration']['service_type']];
                }

                $deliveryMethods = collect($servicesData['delivery_methods'] ?? [])
                    ->filter(fn ($method) => is_string($method) && $method !== '')
                    ->values();

                $isTutorService = collect($serviceTypes)
                    ->contains(fn ($type) => in_array(strtolower((string) $type), ['tutor', 'tutoring'], true));

                $servesRemote = $isTutorService
                    ? $deliveryMethods->contains('online')
                    : ((bool) ($serviceAreaData['remote'] ?? false));

                $servesInPerson = $isTutorService
                    ? $deliveryMethods->contains('in_person_user_location') || $deliveryMethods->contains('in_person_provider_location')
                    : ((bool) ($serviceAreaData['in_person'] ?? true));

                $planUuid = (string) ($subscriptionData['plan_uuid'] ?? '');
                $stripeReady = StripeProviderSubscriptionCheckout::secretConfigured();

                $hasSelectablePlans = SubscriptionPlan::query()
                    ->active()
                    ->forProviderServiceTypeValues($serviceTypes)
                    ->where(function ($query) use ($stripeReady) {
                        $query->where('price_cents', '<=', 0);
                        if ($stripeReady) {
                            $query->orWhere('price_cents', '>', 0);
                        }
                    })
                    ->exists();

                if ($hasSelectablePlans && $planUuid === '') {
                    throw ValidationException::withMessages([
                        'subscription.plan_uuid' => 'Please choose a subscription plan to continue.',
                    ]);
                }

                $selectedPlan = $planUuid !== ''
                    ? SubscriptionPlan::query()
                        ->active()
                        ->forProviderServiceTypeValues($serviceTypes)
                        ->where('uuid', $planUuid)
                        ->first()
                    : null;

                if ($hasSelectablePlans && ! $selectedPlan) {
                    throw ValidationException::withMessages([
                        'subscription.plan_uuid' => 'The selected plan is not available. Please choose another plan.',
                    ]);
                }

                $selectedTotalCents = $selectedPlan ? (int) $selectedPlan->price_cents : 0;

                $serviceProvider = ServiceProvider::create([
                    'user_id' => $user->id,
                    'business_name' => $businessData['business_name'] ?? $user->full_name,
                    'bio' => $businessData['bio'] ?? null,
                    'tagline' => $businessData['tagline'] ?? null,
                    'business_email' => $businessData['business_email'] ?? $user->email,
                    'business_phone' => $businessData['business_phone'] ?? $user->phone,
                    'website' => $businessData['website'] ?? null,
                    'service_types' => $serviceTypes,
                    'specializations' => $servicesData['specializations'] ?? [],
                    'pricing_model' => $pricingData['model'] ?? 'hourly',
                    'hourly_rate' => $pricingData['hourly_rate'] ?? null,
                    'consultation_fee' => $pricingData['consultation_fee'] ?? null,
                    'free_consultation' => $pricingData['free_consultation'] ?? false,
                    'pricing_notes' => $pricingData['notes'] ?? null,
                    'serves_remote' => $servesRemote,
                    'serves_in_person' => $servesInPerson,
                    'service_radius_miles' => $serviceAreaData['radius'] ?? null,
                    'service_areas' => $serviceAreaData['areas'] ?? [],
                    'languages_offered' => $user->languages ?? ['en'],
                    'license_number' => $businessData['license_number'] ?? null,
                    'years_experience' => $businessData['years_experience'] ?? null,
                ]);

                if ($selectedPlan) {
                    ProviderSubscription::query()->create([
                        'service_provider_id' => $serviceProvider->id,
                        'subscription_plan_id' => $selectedPlan->id,
                        'status' => $selectedTotalCents <= 0 ? 'active' : 'incomplete',
                        'started_at' => $selectedTotalCents <= 0 ? now() : null,
                        'current_period_start' => $selectedTotalCents <= 0 ? now() : null,
                        'affiliate_id' => $user->referred_by_affiliate_id,
                        'affiliate_referral_id' => $user->affiliate_referral_id,
                        'affiliate_attribution_type' => 'first_touch',
                        'meta' => [
                            'source' => 'onboarding_mobile',
                            'plan_price_cents' => (int) $selectedPlan->price_cents,
                            'charged_amount_cents' => $selectedTotalCents,
                        ],
                    ]);

                    if ($selectedTotalCents <= 0) {
                        $serviceProvider->update([
                            'subscription_plan' => $selectedPlan->name,
                            'subscription_expires_at' => null,
                            'stripe_subscription_status' => 'active',
                        ]);
                    } else {
                        $providerForCheckout = $serviceProvider;
                        $planForCheckout = $selectedPlan;
                    }
                }

                if ($user->hasRole(UserRole::USER->value)) {
                    UserRoleAccounts::markProviderAccountComplete($user);
                }
            });
        } catch (ValidationException $e) {
            return $this->error('Validation failed', $e->errors(), 422);
        }

        if ($providerForCheckout instanceof ServiceProvider && $planForCheckout instanceof SubscriptionPlan) {
            $checkoutUrl = $this->createStripeCheckoutUrl($request, $providerForCheckout, $planForCheckout);
            if ($checkoutUrl) {
                return $this->success('Checkout required', [
                    'checkout_url' => $checkoutUrl,
                ]);
            }
        }

        return $this->success('Onboarding complete', [
            'user' => (new UserResource($user->refresh()))->resolve(),
        ]);
    }

    private function createStripeCheckoutUrl(Request $request, ServiceProvider $provider, SubscriptionPlan $plan): ?string
    {
        if (! StripeProviderSubscriptionCheckout::secretConfigured()) {
            return null;
        }

        $lineItems = StripeProviderSubscriptionCheckout::lineItemsForPlan($plan);
        if ($lineItems === null) {
            return null;
        }

        try {
            Stripe::setApiKey((string) config('services.stripe.secret'));
            $session = StripeCheckoutSession::create([
                'mode' => 'subscription',
                'customer_email' => $request->user()->email,
                'client_reference_id' => (string) $request->user()->id,
                'success_url' => rtrim(config('app.url'), '/').'/provider/subscriptions?checkout=success',
                'cancel_url' => rtrim(config('app.url'), '/').'/provider/subscriptions?checkout=cancelled',
                'line_items' => $lineItems,
                'metadata' => [
                    'app' => 'provider_subscription',
                    'provider_id' => (string) $provider->id,
                    'user_id' => (string) $request->user()->id,
                    'plan_uuid' => (string) $plan->uuid,
                    'plan_name' => (string) $plan->name,
                    'source' => 'onboarding_mobile',
                ],
                'subscription_data' => [
                    'metadata' => [
                        'provider_id' => (string) $provider->id,
                        'user_id' => (string) $request->user()->id,
                        'plan_uuid' => (string) $plan->uuid,
                        'app' => 'provider_subscription',
                        'source' => 'onboarding_mobile',
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Mobile onboarding Stripe checkout creation failed', [
                'provider_id' => $provider->id,
                'plan_id' => $plan->id,
                'user_id' => $request->user()?->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        $url = $session->url;

        return is_string($url) && trim($url) !== '' ? $url : null;
    }

    private function userSteps(): array
    {
        return [
            ['key' => 'services', 'title' => 'Services', 'description' => 'What kind of help are you looking for?'],
            ['key' => 'complete', 'title' => 'Review', 'description' => 'You are ready to continue'],
        ];
    }

    private function advertiserSteps(): array
    {
        return [
            ['key' => 'address', 'title' => 'Address', 'description' => 'Where should we localize your ad audience?'],
            ['key' => 'complete', 'title' => 'Review', 'description' => 'Finish setup'],
        ];
    }

    private function providerSteps(): array
    {
        return [
            ['key' => 'location', 'title' => 'Address', 'description' => 'Your business street address'],
            ['key' => 'business', 'title' => 'Business', 'description' => 'Tell clients about your practice'],
            ['key' => 'pricing', 'title' => 'Pricing', 'description' => 'How you charge'],
            ['key' => 'subscription', 'title' => 'Subscription', 'description' => 'Pick your provider plan'],
            ['key' => 'complete', 'title' => 'Review', 'description' => 'Finish setup'],
        ];
    }

    /**
     * Merge onboarding_data with existing User + ServiceProvider fields so
     * admin-created accounts see their pre-populated data in the forms.
     *
     * @return array<string, mixed>
     */
    private function mergedExistingData(\App\Models\User $user): array
    {
        $onboarding = $user->onboarding_data ?? [];

        $location = $onboarding['location'] ?? [];
        if (empty($location['city']) && filled($user->city)) {
            $location['city'] = $user->city;
        }
        if (empty($location['state']) && filled($user->state)) {
            $location['state'] = $user->state;
        }
        if (empty($location['country']) && filled($user->country)) {
            $location['country'] = $user->country;
        }
        if (empty($location['postal_code']) && filled($user->postal_code)) {
            $location['postal_code'] = $user->postal_code;
        }
        if (empty($location['street']) && filled($user->address)) {
            $location['street'] = $user->address;
        }
        if ($location !== ($onboarding['location'] ?? [])) {
            $onboarding['location'] = $location;
        }

        $provider = $user->serviceProvider;
        if ($provider) {
            $services = $onboarding['services'] ?? [];
            if (empty($services['types']) && is_array($provider->service_types) && $provider->service_types !== []) {
                $services['types'] = $provider->service_types;
            }
            if ($services !== ($onboarding['services'] ?? [])) {
                $onboarding['services'] = $services;
            }

            $business = $onboarding['business'] ?? [];
            if (empty($business['business_name']) && filled($provider->business_name)) {
                $business['business_name'] = $provider->business_name;
            }
            if (empty($business['tagline']) && filled($provider->tagline)) {
                $business['tagline'] = $provider->tagline;
            }
            if (empty($business['bio']) && filled($provider->bio)) {
                $business['bio'] = $provider->bio;
            }
            if (empty($business['license_number']) && filled($provider->license_number)) {
                $business['license_number'] = $provider->license_number;
            }
            if (! isset($business['years_experience']) && $provider->years_experience !== null) {
                $business['years_experience'] = $provider->years_experience;
            }
            if ($business !== ($onboarding['business'] ?? [])) {
                $onboarding['business'] = $business;
            }
        }

        return $onboarding;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function success(string $message, array $data, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * @param  array<string, mixed>  $errors
     */
    private function error(string $message, array $errors = [], int $status = 422): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors === [] ? (object) [] : $errors,
        ], $status);
    }
}
