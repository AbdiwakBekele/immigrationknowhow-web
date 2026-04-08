<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\ServiceProvider;
use App\Support\CountryOptions;
use App\Support\LanguageOptions;
use App\Support\ServiceTypeOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $user = auth()->user();

        if ($user->hasCompletedOnboarding()) {
            return $this->redirectToDashboard();
        }

        $isProvider = $user->followsProviderOnboarding();
        if ($isProvider && ! $user->isProvider()) {
            $user->assignRole(UserRole::PROVIDER->value);
        }
        $requestedStep = (int) $request->integer('step', $isProvider ? 4 : 2);
        $initialStep = $isProvider
            ? max(4, min(5, $requestedStep))
            : max(2, min(3, $requestedStep));

        return Inertia::render('Onboarding/Index', [
            'user' => $user->only(['id', 'first_name', 'last_name', 'email', 'city', 'country', 'preferred_language']),
            'initialStep' => $initialStep,
            'isProvider' => $isProvider,
            'serviceTypes' => $isProvider
                ? ServiceTypeOptions::selectOptions('provider')
                : ServiceTypeOptions::userIntakeOptions(),
            'countryOptions' => CountryOptions::selectOptions(),
            'languageOptions' => LanguageOptions::selectOptions(),
            'existingData' => $user->onboarding_data ?? [],
            'steps' => $isProvider ? $this->getProviderSteps() : $this->getUserSteps(),
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
            ['key' => 'business', 'title' => 'Business', 'description' => 'Tell clients about your practice'],
            ['key' => 'pricing', 'title' => 'Pricing', 'description' => 'How you charge'],
            ['key' => 'service-area', 'title' => 'Service area', 'description' => 'How you meet clients'],
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

    public function complete(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $isProvider = $user->followsProviderOnboarding();

        if ($isProvider && ! $user->isProvider()) {
            $user->assignRole(UserRole::PROVIDER->value);
        }

        DB::transaction(function () use ($user, $request, $isProvider) {
            $data = $request->all();
            $onboardingData = $user->onboarding_data ?? [];

            $user->update([
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

                $serviceTypes = $servicesData['types'] ?? [];
                if ($serviceTypes === [] && ! empty($onboardingData['registration']['service_type'])) {
                    $serviceTypes = [$onboardingData['registration']['service_type']];
                }

                ServiceProvider::create([
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
            }
        });

        return $this->redirectToDashboard()->with('success', 'Welcome! Your profile is complete.');
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
