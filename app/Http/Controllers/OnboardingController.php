<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\ProviderSubscription;
use App\Models\ServiceProvider;
use App\Models\ServiceTypeOption;
use App\Models\SubscriptionPlan;
use App\Support\CountryOptions;
use App\Support\LanguageOptions;
use App\Support\PhoneDialOptions;
use App\Support\ServiceTypeOptions;
use App\Support\ProviderSubscriptionPromo;
use App\Support\StripeProviderSubscriptionCheckout;
use App\Support\UserRoleAccounts;
use App\Support\UsStateOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Stripe;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class OnboardingController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        if ($request->user()?->isAdvertiser()) {
            $params = $request->filled('step')
                ? ['step' => $request->integer('step')]
                : [];

            return redirect()->route('onboarding.advertiser', $params);
        }

        // Compat entry-point: redirect to dedicated onboarding pages.
        $params = $request->filled('step')
            ? ['step' => $request->integer('step')]
            : [];

        return $request->user()?->followsProviderOnboarding()
            ? redirect()->route('onboarding.provider', $params)
            : redirect()->route('onboarding.user', $params);
    }

    public function user(Request $request): Response|RedirectResponse
    {
        if ($request->user()?->isAdvertiser()) {
            $params = $request->filled('step')
                ? ['step' => $request->integer('step')]
                : [];

            return redirect()->route('onboarding.advertiser', $params);
        }

        if ($request->user()?->followsProviderOnboarding()) {
            $params = $request->filled('step')
                ? ['step' => $request->integer('step')]
                : [];

            return redirect()->route('onboarding.provider', $params);
        }

        return $this->renderOnboardingPage($request, false, 'user');
    }

    public function provider(Request $request): Response|RedirectResponse
    {
        if ($request->user()?->isAdvertiser()) {
            $params = $request->filled('step')
                ? ['step' => $request->integer('step')]
                : [];

            return redirect()->route('onboarding.advertiser', $params);
        }

        if (! $request->user()?->followsProviderOnboarding()) {
            $params = $request->filled('step')
                ? ['step' => $request->integer('step')]
                : [];

            return redirect()->route('onboarding.user', $params);
        }

        return $this->renderOnboardingPage($request, false, 'provider');
    }

    public function advertiser(Request $request): Response|RedirectResponse
    {
        if (! $request->user()?->isAdvertiser()) {
            $params = $request->filled('step')
                ? ['step' => $request->integer('step')]
                : [];

            return redirect()->route('onboarding.index', $params);
        }

        return $this->renderOnboardingPage($request, true, 'advertiser');
    }

    private function renderOnboardingPage(Request $request, bool $forceAdvertiser, string $mode): Response|RedirectResponse
    {
        $user = auth()->user();

        if ($user->hasCompletedOnboarding() && ! UserRoleAccounts::isAddingProviderAccount($user)) {
            return $this->redirectToDashboard();
        }

        $needsPhone = ! $user->hasCompletedSignupPhoneStep() && ! $user->isAdmin() && ! $user->isAffiliate();
        $isAdvertiser = $forceAdvertiser || $user->isAdvertiser();
        $isProvider = $mode === 'provider';
        $requestedStep = (int) $request->integer('step', $isProvider ? 2 : 2);

        Log::channel('single')->info('FLOW_DEBUG onboarding.index entry', [
            'user_id' => $user->id,
            'requested_step' => $requestedStep,
            'needs_phone_verification' => $needsPhone,
            'has_completed_signup_address_step' => $user->hasCompletedSignupAddressStep(),
            'phone_verified_at' => $user->phone_verified_at,
            'phone_present' => filled($user->phone),
            'is_provider_flow' => $isProvider,
        ]);

        // Providers must complete coverage (address-detail) before phone verification (step 3).
        if ($needsPhone && $isProvider && ! $user->hasCompletedSignupAddressStep()) {
            return redirect()->route('address-detail');
        }

        if ($isProvider && ! $user->isProvider()) {
            $user->assignRole(UserRole::PROVIDER->value);
        }

        if ($mode === 'user') {
            $initialStep = $needsPhone ? max(2, min(3, $requestedStep)) : 4;
        } elseif ($mode === 'provider') {
            if ($request->filled('step') && $requestedStep >= 2 && $requestedStep <= 7) {
                $initialStep = $requestedStep;
            } elseif ($needsPhone) {
                $initialStep = 3;
            } else {
                $initialStep = 4;
            }
        } else {
            // advertiser: same step 2→3 sequencing as users (address before OTP); clamp only signup steps here
            $initialStep = max(2, min(3, $requestedStep));
        }

        Log::channel('single')->info('FLOW_DEBUG onboarding.index resolved step', [
            'user_id' => $user->id,
            'requested_step' => $requestedStep,
            'initial_step' => $initialStep,
            'needs_phone_verification' => $needsPhone,
            'is_provider_flow' => $isProvider,
        ]);

        $providerProfile = $isProvider
            ? ServiceProvider::query()->where('user_id', $user->id)->first()
            : null;

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

        $component = match ($mode) {
            'provider' => 'Onboarding/Provider',
            'user' => 'Onboarding/User',
            'advertiser' => 'Onboarding/Advertiser',
            default => 'Onboarding/User',
        };

        return Inertia::render($component, [
            'user' => $user->only(['id', 'first_name', 'last_name', 'email', 'address', 'city', 'state', 'postal_code', 'country', 'preferred_language']),
            'initialStep' => $initialStep,
            'requiresPhoneVerification' => $needsPhone,
            'phoneAlreadyVerified' => ! $needsPhone && filled($user->phone),
            'phoneVerification' => $isProvider || $needsPhone
                ? [
                    'phone' => $user->phone ?? '',
                    'phoneDialOptions' => PhoneDialOptions::selectOptions(),
                ]
                : null,
            'isProvider' => $isProvider,
            'serviceTypes' => $isProvider
                ? ServiceTypeOptions::selectOptions('provider')
                : ($isAdvertiser ? [] : ServiceTypeOptions::selectOptions('user')),
            'countryOptions' => CountryOptions::selectOptions(),
            'stateOptions' => \App\Support\UsStateOptions::selectOptions($user->country ?? 'US'),
            'languageOptions' => LanguageOptions::selectOptions(),
            'existingData' => $user->onboarding_data ?? [],
            'steps' => $isProvider
                ? $this->getProviderSteps()
                : ($isAdvertiser ? $this->getAdvertiserSteps() : $this->getUserSteps()),
            'subscriptionPlans' => $subscriptionPlans,
            'stripeBillingReady' => StripeProviderSubscriptionCheckout::secretConfigured(),
            'providerSubscriptionPromo' => $isProvider
                ? ProviderSubscriptionPromo::promoPayload($providerProfile)
                : null,
            'addingProviderAccount' => UserRoleAccounts::isAddingProviderAccount($user),
        ]);
    }

    protected function getUserSteps(): array
    {
        return [
            ['key' => 'services', 'title' => 'Services', 'description' => 'What kind of help are you looking for?'],
            ['key' => 'complete', 'title' => 'Review', 'description' => 'You are ready to continue'],
        ];
    }

    protected function getProviderSteps(): array
    {
        return [
            ['key' => 'location', 'title' => 'Address', 'description' => 'Your business street address'],
            ['key' => 'business', 'title' => 'Business', 'description' => 'Tell clients about your practice'],
            ['key' => 'pricing', 'title' => 'Pricing', 'description' => 'How you charge'],
            ['key' => 'subscription', 'title' => 'Subscription', 'description' => 'Pick your provider plan'],
            ['key' => 'complete', 'title' => 'Review', 'description' => 'Finish setup'],
        ];
    }

    protected function getAdvertiserSteps(): array
    {
        return [
            ['key' => 'address', 'title' => 'Address', 'description' => 'Where should we localize your ad audience?'],
            ['key' => 'complete', 'title' => 'Review', 'description' => 'Finish setup'],
        ];
    }

    public function saveProgress(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $step = $request->input('step');
        $data = $request->input('data', []);

        $onboardingData = array_merge($user->onboarding_data ?? [], [
            $step => $data,
            'last_completed_step' => $step,
        ]);

        $user->update(['onboarding_data' => $onboardingData]);

        return back()->with('success', 'Progress saved');
    }

    public function complete(Request $request): RedirectResponse|SymfonyResponse
    {
        $user = auth()->user();
        $userServiceTypeValues = ServiceTypeOptions::values('user');

        $request->validate([
            'services_needed' => ['nullable', 'array', 'max:8'],
            'services_needed.*' => ['string', Rule::in($userServiceTypeValues)],
        ]);

        if (! $user->hasCompletedSignupPhoneStep() && ! $user->isAdmin() && ! $user->isAffiliate()) {
            if (! $user->hasCompletedSignupAddressStep()) {
                return redirect()->route('address-detail');
            }

            return redirect()->route($user->isAdvertiser() ? 'onboarding.advertiser' : 'onboarding.index', ['step' => 3]);
        }

        $isProvider = $user->followsProviderOnboarding();
        $providerForCheckout = null;
        $planForCheckout = null;

        if ($isProvider && ! $user->isProvider()) {
            $user->assignRole(UserRole::PROVIDER->value);
        }

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

            if ($isProvider) {
                $businessData = $onboardingData['business'] ?? [];
                $servicesData = array_merge($onboardingData['services'] ?? [], $data['services'] ?? []);
                $pricingData = $onboardingData['pricing'] ?? [];
                $serviceAreaData = $onboardingData['service-area'] ?? [];
                $subscriptionData = $onboardingData['subscription'] ?? [];
                $serviceTypes = $servicesData['types'] ?? [];
                if ($serviceTypes === [] && ! empty($onboardingData['registration']['service_type'])) {
                    $serviceTypes = [$onboardingData['registration']['service_type']];
                }
                $primaryProviderServiceType = $this->resolvePrimaryProviderServiceTypeValueFromServiceTypes($serviceTypes, $onboardingData);
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
                            'source' => 'onboarding',
                            'primary_service_type' => $primaryProviderServiceType,
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

                UserRoleAccounts::markProviderAccountComplete($user);
            }
        });

        if ($providerForCheckout instanceof ServiceProvider && $planForCheckout instanceof SubscriptionPlan) {
            $checkoutUrl = $this->createStripeCheckoutUrl($request, $providerForCheckout, $planForCheckout);
            if ($checkoutUrl !== null) {
                return Inertia::location($checkoutUrl);
            }

            return redirect()->route('provider.dashboard')->with('error', 'Unable to start Stripe checkout. Please try subscribing again from your dashboard.');
        }

        return $this->redirectToDashboard()->with('success', 'Welcome! Your profile is complete.');
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

        $planPriceCents = (int) $plan->price_cents;

        try {
            Stripe::setApiKey((string) config('services.stripe.secret'));
            $session = StripeCheckoutSession::create([
                'mode' => 'subscription',
                'customer_email' => $request->user()->email,
                'client_reference_id' => (string) $request->user()->id,
                // Include session_id so provider.subscriptions.index can verify + sync the Stripe subscription.
                'success_url' => route('provider.subscriptions.index', [], true).'?checkout=success&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('provider.subscriptions.index', [], true).'?checkout=cancelled',
                'line_items' => $lineItems,
                'metadata' => [
                    'app' => 'provider_subscription',
                    'provider_id' => (string) $provider->id,
                    'user_id' => (string) $request->user()->id,
                    'plan_uuid' => (string) $plan->uuid,
                    'plan_name' => (string) $plan->name,
                    'source' => 'onboarding',
                    'plan_price_cents' => (string) $planPriceCents,
                    'charged_amount_cents' => (string) $planPriceCents,
                ],
                'subscription_data' => ProviderSubscriptionPromo::stripeSubscriptionData($provider, [
                    'provider_id' => (string) $provider->id,
                    'user_id' => (string) $request->user()->id,
                    'plan_uuid' => (string) $plan->uuid,
                    'app' => 'provider_subscription',
                    'source' => 'onboarding',
                    'plan_price_cents' => (string) $planPriceCents,
                    'charged_amount_cents' => (string) $planPriceCents,
                ]),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Onboarding Stripe checkout creation failed', [
                'provider_id' => $provider->id,
                'plan_id' => $plan->id,
                'user_id' => $request->user()?->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        // If onboarding created an "incomplete" placeholder row, attach the Checkout Session id
        // so support/debugging can trace a Stripe checkout back to a local subscription row.
        try {
            \App\Models\ProviderSubscription::query()
                ->where('service_provider_id', $provider->id)
                ->whereNull('stripe_subscription_id')
                ->where('status', 'incomplete')
                ->where('subscription_plan_id', $plan->id)
                ->latest('id')
                ->limit(1)
                ->update([
                    'stripe_checkout_session_id' => (string) $session->id,
                ]);
        } catch (\Throwable) {
            // ignore
        }

        $checkoutUrl = $session->url;
        if (! is_string($checkoutUrl) || trim($checkoutUrl) === '') {
            return null;
        }

        return $checkoutUrl;
    }

    private function resolvePrimaryProviderServiceTypeValueFromOnboardingData(array $onboardingData): ?string
    {
        $serviceTypes = data_get($onboardingData, 'services.types', []);
        if (! is_array($serviceTypes)) {
            $serviceTypes = [];
        }

        return $this->resolvePrimaryProviderServiceTypeValueFromServiceTypes($serviceTypes, $onboardingData);
    }

    private function resolvePrimaryProviderServiceTypeValueFromServiceTypes(array $serviceTypes, array $onboardingData): ?string
    {
        $candidate = collect($serviceTypes)
            ->first(fn ($value) => is_string($value) && trim($value) !== '');

        if (! is_string($candidate) || trim($candidate) === '') {
            $candidate = data_get($onboardingData, 'registration.service_type');
        }

        if (! is_string($candidate) || trim($candidate) === '') {
            return null;
        }

        $candidate = trim($candidate);
        $exists = ServiceTypeOption::query()
            ->where('value', $candidate)
            ->where('for_provider', true)
            ->where('is_active', true)
            ->exists();

        return $exists ? $candidate : null;
    }

    protected function redirectToDashboard(): RedirectResponse
    {
        $user = auth()->user();

        return redirect(UserRoleAccounts::dashboardRouteFor($user, session(UserRoleAccounts::SESSION_ACTIVE_PORTAL)));
    }
}
