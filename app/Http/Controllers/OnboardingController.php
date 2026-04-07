<?php

namespace App\Http\Controllers;

use App\Enums\ServiceType;
use App\Enums\UserRole;
use App\Models\ServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    protected array $languages = [
        'en' => 'English',
        'es' => 'Spanish',
        'zh' => 'Chinese (Mandarin)',
        'hi' => 'Hindi',
        'ar' => 'Arabic',
        'pt' => 'Portuguese',
        'fr' => 'French',
        'de' => 'German',
        'ja' => 'Japanese',
        'ko' => 'Korean',
        'vi' => 'Vietnamese',
        'tl' => 'Tagalog',
        'ru' => 'Russian',
        'it' => 'Italian',
        'pl' => 'Polish',
        'uk' => 'Ukrainian',
        'fa' => 'Persian',
        'tr' => 'Turkish',
        'th' => 'Thai',
        'he' => 'Hebrew',
    ];

    public function index(): Response|RedirectResponse
    {
        $user = auth()->user();

        if ($user->hasCompletedOnboarding()) {
            return $this->redirectToDashboard();
        }

        $isProvider = $user->hasRole(UserRole::PROVIDER->value);

        return Inertia::render('Onboarding/Index', [
            'user' => $user->only(['id', 'first_name', 'last_name', 'email']),
            'isProvider' => $isProvider,
            'serviceTypes' => ServiceType::options(),
            'languages' => $this->languages,
            'existingData' => $user->onboarding_data ?? [],
            'steps' => $isProvider ? $this->getProviderSteps() : $this->getUserSteps(),
        ]);
    }

    protected function getUserSteps(): array
    {
        return [
            ['key' => 'welcome', 'title' => 'Welcome', 'description' => 'Get started with your profile'],
            ['key' => 'services', 'title' => 'Services Needed', 'description' => 'What services are you looking for?'],
            ['key' => 'location', 'title' => 'Location', 'description' => 'Where are you located?'],
            ['key' => 'language', 'title' => 'Language', 'description' => 'Your language preferences'],
            ['key' => 'complete', 'title' => 'All Set!', 'description' => 'Your profile is ready'],
        ];
    }

    protected function getProviderSteps(): array
    {
        return [
            ['key' => 'welcome', 'title' => 'Welcome', 'description' => 'Set up your provider profile'],
            ['key' => 'business', 'title' => 'Business Info', 'description' => 'Tell us about your business'],
            ['key' => 'services', 'title' => 'Services Offered', 'description' => 'What services do you provide?'],
            ['key' => 'pricing', 'title' => 'Pricing', 'description' => 'Set your rates'],
            ['key' => 'service-area', 'title' => 'Service Area', 'description' => 'Where do you serve clients?'],
            ['key' => 'languages', 'title' => 'Languages', 'description' => 'Languages you can serve'],
            ['key' => 'complete', 'title' => 'All Set!', 'description' => 'Your profile is ready'],
        ];
    }

    public function saveProgress(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $step = $request->input('step');
        $data = $request->input('data', []);

        // Merge with existing onboarding data
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
        $isProvider = $user->hasRole(UserRole::PROVIDER->value);

        DB::transaction(function () use ($user, $request, $isProvider) {
            $data = $request->all();
            $onboardingData = $user->onboarding_data ?? [];

            // Update user profile
            $user->update([
                'city' => $data['city'] ?? $onboardingData['location']['city'] ?? null,
                'state' => $data['state'] ?? $onboardingData['location']['state'] ?? null,
                'country' => $data['country'] ?? $onboardingData['location']['country'] ?? 'US',
                'postal_code' => $data['postal_code'] ?? $onboardingData['location']['postal_code'] ?? null,
                'languages' => $data['languages'] ?? $onboardingData['language']['languages'] ?? ['en'],
                'preferred_language' => $data['preferred_language'] ?? $onboardingData['language']['preferred'] ?? 'en',
                'onboarding_completed' => true,
                'onboarding_data' => $onboardingData,
                'onboarding_completed_at' => now(),
            ]);

            // Create provider profile if applicable
            if ($isProvider) {
                $businessData = $onboardingData['business'] ?? [];
                $servicesData = $onboardingData['services'] ?? [];
                $pricingData = $onboardingData['pricing'] ?? [];
                $serviceAreaData = $onboardingData['service-area'] ?? [];
                $languagesData = $onboardingData['languages'] ?? [];

                ServiceProvider::create([
                    'user_id' => $user->id,
                    'business_name' => $businessData['business_name'] ?? $user->full_name,
                    'bio' => $businessData['bio'] ?? null,
                    'tagline' => $businessData['tagline'] ?? null,
                    'business_email' => $businessData['business_email'] ?? $user->email,
                    'business_phone' => $businessData['business_phone'] ?? $user->phone,
                    'website' => $businessData['website'] ?? null,
                    'service_types' => $servicesData['types'] ?? [],
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
                    'languages_offered' => $languagesData['offered'] ?? ['en'],
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
