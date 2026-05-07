<?php

namespace App\Http\Controllers\Provider;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Review;
use App\Models\ServiceTypeOption;
use App\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $user = auth()->user();
        $provider = $user->serviceProvider;

        // If no provider profile exists, reset onboarding and redirect
        if (! $provider) {
            $user->update([
                'onboarding_completed' => false,
                'onboarding_completed_at' => null,
            ]);

            return redirect()->route('onboarding.index')
                ->with('warning', 'Please complete your provider profile setup.');
        }

        // Calculate stats
        $stats = $this->calculateStats($provider);

        // Recent leads
        $recentLeads = Lead::where('service_provider_id', $provider->id)
            ->whereHas('conversation', function ($q) use ($user) {
                $q->forUser($user)->forServiceInquiries();
            })
            ->with([
                'user:id,first_name,last_name,avatar,email,phone',
                'conversation:id,lead_id,uuid',
            ])
            ->latest()
            ->limit(5)
            ->get();

        // Recent reviews
        $recentReviews = Review::where('service_provider_id', $provider->id)
            ->approved()
            ->with(['user:id,first_name,last_name,avatar'])
            ->latest()
            ->limit(3)
            ->get();

        // Leads chart data (last 30 days)
        $leadsChartData = Lead::where('service_provider_id', $provider->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $stripeSecret = config('services.stripe.secret');
        $hasActivePlan = SubscriptionPlan::query()
            ->active()
            ->whereNotNull('stripe_price_id')
            ->exists();
        $isSubscriptionCheckoutConfigured = is_string($stripeSecret) && $stripeSecret !== '' && $hasActivePlan;

        return Inertia::render('Provider/Dashboard', [
            'stats' => $stats,
            'recentLeads' => $recentLeads,
            'recentReviews' => $recentReviews,
            'leadsChartData' => $leadsChartData,
            'subscriptionCheckoutConfigured' => $isSubscriptionCheckoutConfigured,
            'provider' => $provider->only([
                'id', 'slug', 'business_name', 'average_rating', 'total_reviews',
                'background_check_status', 'is_featured', 'profile_views',
                'subscription_plan', 'subscription_expires_at', 'stripe_subscription_status', 'health_certificates',
            ]) + [
                'requires_background_check' => $this->providerRequiresBackgroundCheck($provider),
                'requires_certificate_upload' => $this->providerRequiresCertificateUpload($provider),
                'needs_certificate_upload' => $this->providerNeedsCertificateUpload($provider),
            ],
        ]);
    }

    private function providerRequiresBackgroundCheck($provider): bool
    {
        $providerTypes = collect($provider->service_types ?? [])
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn ($value) => $this->canonicalServiceTypeValue((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($providerTypes === []) {
            return false;
        }

        if (
            Schema::hasTable('service_type_options')
            && Schema::hasColumn('service_type_options', 'requires_background_check')
        ) {
            $requiredTypes = ServiceTypeOption::query()
                ->where('requires_background_check', true)
                ->pluck('value')
                ->map(fn ($value) => $this->canonicalServiceTypeValue((string) $value))
                ->filter()
                ->unique();

            return collect($providerTypes)->intersect($requiredTypes)->isNotEmpty();
        }

        return collect($providerTypes)
            ->intersect(['pet_sitter', 'petsitter', 'babysitter', 'baby_sitter', 'tutor'])
            ->isNotEmpty();
    }

    private function providerRequiresCertificateUpload($provider): bool
    {
        $providerTypes = collect($provider->service_types ?? [])
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn ($value) => $this->canonicalServiceTypeValue((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($providerTypes === []) {
            return false;
        }

        $defaultRequiredTypes = collect(['pet_sitter', 'babysitter', 'health_navigator']);

        if (collect($providerTypes)->intersect($defaultRequiredTypes)->isNotEmpty()) {
            return true;
        }

        if (
            Schema::hasTable('service_type_options')
            && Schema::hasColumn('service_type_options', 'include_certificate')
        ) {
            $requiredTypes = ServiceTypeOption::query()
                ->where('include_certificate', true)
                ->pluck('value')
                ->map(fn ($value) => $this->canonicalServiceTypeValue((string) $value))
                ->filter()
                ->unique();

            return collect($providerTypes)->intersect($requiredTypes)->isNotEmpty();
        }

        return collect($providerTypes)
            ->intersect(['pet_sitter', 'petsitter', 'babysitter', 'baby_sitter', 'health_navigator', 'healthcare_navigator', 'healthnavigator'])
            ->isNotEmpty();
    }

    private function providerNeedsCertificateUpload($provider): bool
    {
        if (! $this->providerRequiresCertificateUpload($provider)) {
            return false;
        }

        $uploadedCertificates = collect($provider->health_certificates ?? [])
            ->contains(fn ($certificate) => is_array($certificate)
                && is_string($certificate['file_path'] ?? null)
                && trim((string) $certificate['file_path']) !== '');

        return ! $uploadedCertificates;
    }

    private function normalizeServiceTypeValue(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/i', '_', strtolower(trim($value))), '_');
    }

    private function canonicalServiceTypeValue(string $value): string
    {
        $normalized = $this->normalizeServiceTypeValue($value);

        if (($normalized === 'pet_sitter' || str_contains($normalized, 'pet')) && str_contains($normalized, 'sitter')) {
            return 'pet_sitter';
        }

        if (str_contains($normalized, 'babysitter') || (str_contains($normalized, 'baby') && str_contains($normalized, 'sitter'))) {
            return 'babysitter';
        }

        if (str_contains($normalized, 'tutor')) {
            return 'tutor';
        }

        if ((str_contains($normalized, 'health') || str_contains($normalized, 'healthcare')) && str_contains($normalized, 'navigator')) {
            return 'health_navigator';
        }

        return $normalized;
    }

    protected function calculateStats($provider): array
    {
        $now = now();
        $thirtyDaysAgo = $now->copy()->subDays(30);
        $sixtyDaysAgo = $now->copy()->subDays(60);

        // Total leads
        $totalLeads = Lead::where('service_provider_id', $provider->id)->count();

        // New leads (unviewed)
        $newLeads = Lead::where('service_provider_id', $provider->id)
            ->new()
            ->count();

        // Open leads
        $openLeads = Lead::where('service_provider_id', $provider->id)
            ->open()
            ->count();

        // Converted leads
        $convertedLeads = Lead::where('service_provider_id', $provider->id)
            ->where('status', LeadStatus::CONVERTED)
            ->count();

        // Conversion rate
        $conversionRate = $totalLeads > 0 ? round(($convertedLeads / $totalLeads) * 100, 1) : 0;

        // Profile views
        $profileViews = $provider->profile_views ?? 0;

        // Calculate trends (compare last 30 days to previous 30 days)
        $leadsThisMonth = Lead::where('service_provider_id', $provider->id)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->count();

        $leadsLastMonth = Lead::where('service_provider_id', $provider->id)
            ->whereBetween('created_at', [$sixtyDaysAgo, $thirtyDaysAgo])
            ->count();

        $leadsTrend = $leadsLastMonth > 0
            ? round((($leadsThisMonth - $leadsLastMonth) / $leadsLastMonth) * 100, 1)
            : ($leadsThisMonth > 0 ? 100 : 0);

        return [
            'totalLeads' => $totalLeads,
            'newLeads' => $newLeads,
            'openLeads' => $openLeads,
            'convertedLeads' => $convertedLeads,
            'conversionRate' => $conversionRate,
            'profileViews' => $profileViews,
            'leadsTrend' => $leadsTrend,
            'viewsTrend' => 0,
        ];
    }
}
