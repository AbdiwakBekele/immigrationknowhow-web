<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\ProviderSubscription;
use App\Models\ServiceProvider;
use App\Models\SubscriptionPlan;
use App\Support\CountryOptions;
use App\Support\LanguageOptions;
use App\Support\PhoneDialOptions;
use App\Support\ServiceTypeOptions;
use App\Support\StripeProviderSubscriptionCheckout;
use App\Support\UsStateOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        $user = auth()->user();

        if ($user->hasCompletedOnboarding()) {
            return $this->redirectToDashboard();
        }

        $needsPhone = ! $user->hasCompletedSignupPhoneStep() && ! $user->isAdmin() && ! $user->isAffiliate();
        $requestedStep = (int) $request->integer('step', 4);

        Log::channel('single')->info('FLOW_DEBUG onboarding.index entry', [
            'user_id' => $user->id,
            'requested_step' => $requestedStep,
            'needs_phone_verification' => $needsPhone,
            'has_completed_signup_address_step' => $user->hasCompletedSignupAddressStep(),
            'phone_verified_at' => $user->phone_verified_at,
            'phone_present' => filled($user->phone),
            'is_provider_flow' => $user->followsProviderOnboarding(),
        ]);

        if ($needsPhone && ! $user->hasCompletedSignupAddressStep()) {
            return redirect()->route('address-detail');
        }

        $isProvider = $user->followsProviderOnboarding();
        if ($isProvider && ! $user->isProvider()) {
            $user->assignRole(UserRole::PROVIDER->value);
        }

        if ($needsPhone) {
            $initialStep = 3;
        } else {
            $initialStep = $isProvider
                ? max(4, min(7, $requestedStep))
                : max(4, min(5, $requestedStep));
        }

        Log::channel('single')->info('FLOW_DEBUG onboarding.index resolved step', [
            'user_id' => $user->id,
            'requested_step' => $requestedStep,
            'initial_step' => $initialStep,
            'needs_phone_verification' => $needsPhone,
            'is_provider_flow' => $isProvider,
        ]);

        $subscriptionPlans = $isProvider
            ? SubscriptionPlan::query()
                ->active()
                ->orderByDesc('is_featured')
                ->orderBy('sort_order')
                ->get(['id', 'uuid', 'name', 'description', 'price_cents', 'currency', 'billing_cycle', 'features', 'is_featured', 'stripe_price_id'])
            : collect();

        return Inertia::render('Onboarding/Index', [
            'user' => $user->only(['id', 'first_name', 'last_name', 'email', 'address', 'city', 'state', 'postal_code', 'country', 'preferred_language']),
            'initialStep' => $initialStep,
            'requiresPhoneVerification' => $needsPhone,
            'phoneVerification' => $needsPhone
                ? [
                    'phone' => $user->phone ?? '',
                    'phoneDialOptions' => PhoneDialOptions::selectOptions(),
                ]
                : null,
            'isProvider' => $isProvider,
            'serviceTypes' => $isProvider
                ? ServiceTypeOptions::selectOptions('provider')
                : ServiceTypeOptions::userIntakeOptions(),
            'countryOptions' => CountryOptions::selectOptions(),
            'stateOptions' => UsStateOptions::selectOptions($user->country ?? 'US'),
            'languageOptions' => LanguageOptions::selectOptions(),
            'existingData' => $user->onboarding_data ?? [],
            'steps' => $isProvider ? $this->getProviderSteps() : $this->getUserSteps(),
            'subscriptionPlans' => $subscriptionPlans,
            'stripeBillingReady' => StripeProviderSubscriptionCheckout::secretConfigured(),
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

        if (! $user->hasCompletedSignupPhoneStep() && ! $user->isAdmin() && ! $user->isAffiliate()) {
            if (! $user->hasCompletedSignupAddressStep()) {
                return redirect()->route('address-detail');
            }

            return redirect()->route('onboarding.index', ['step' => 3]);
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

                $planUuid = (string) ($subscriptionData['plan_uuid'] ?? '');
                $stripeReady = StripeProviderSubscriptionCheckout::secretConfigured();
                $hasSelectablePlans = SubscriptionPlan::query()
                    ->active()
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
                        ->where('uuid', $planUuid)
                        ->first()
                    : null;
                if ($hasSelectablePlans && ! $selectedPlan) {
                    throw ValidationException::withMessages([
                        'subscription.plan_uuid' => 'The selected plan is not available. Please choose another plan.',
                    ]);
                }

                $serviceTypes = $servicesData['types'] ?? [];
                if ($serviceTypes === [] && ! empty($onboardingData['registration']['service_type'])) {
                    $serviceTypes = [$onboardingData['registration']['service_type']];
                }

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
                    'serves_remote' => $serviceAreaData['remote'] ?? false,
                    'serves_in_person' => $serviceAreaData['in_person'] ?? true,
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
                        'status' => (int) $selectedPlan->price_cents <= 0 ? 'active' : 'incomplete',
                        'started_at' => (int) $selectedPlan->price_cents <= 0 ? now() : null,
                        'current_period_start' => (int) $selectedPlan->price_cents <= 0 ? now() : null,
                        'affiliate_id' => $user->referred_by_affiliate_id,
                        'affiliate_referral_id' => $user->affiliate_referral_id,
                        'affiliate_attribution_type' => 'first_touch',
                        'meta' => ['source' => 'onboarding'],
                    ]);

                    if ((int) $selectedPlan->price_cents <= 0) {
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

        Stripe::setApiKey((string) config('services.stripe.secret'));
        $session = StripeCheckoutSession::create([
            'mode' => 'subscription',
            'customer_email' => $request->user()->email,
            'client_reference_id' => (string) $request->user()->id,
            'success_url' => route('provider.subscriptions.index', [], true).'?checkout=success',
            'cancel_url' => route('provider.subscriptions.index', [], true).'?checkout=cancelled',
            'line_items' => $lineItems,
            'metadata' => [
                'app' => 'provider_subscription',
                'provider_id' => (string) $provider->id,
                'user_id' => (string) $request->user()->id,
                'plan_uuid' => (string) $plan->uuid,
                'plan_name' => (string) $plan->name,
                'source' => 'onboarding',
            ],
            'subscription_data' => [
                'metadata' => [
                    'provider_id' => (string) $provider->id,
                    'user_id' => (string) $request->user()->id,
                    'plan_uuid' => (string) $plan->uuid,
                    'app' => 'provider_subscription',
                    'source' => 'onboarding',
                ],
            ],
        ]);

        $checkoutUrl = $session->url;
        if (! is_string($checkoutUrl) || trim($checkoutUrl) === '') {
            return null;
        }

        return $checkoutUrl;
    }

    protected function redirectToDashboard(): RedirectResponse
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->isProvider()) {
            return redirect()->route('provider.dashboard');
        }

        return redirect()->route('dashboard');
    }
}
