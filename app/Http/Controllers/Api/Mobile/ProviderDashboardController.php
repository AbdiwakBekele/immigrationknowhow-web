<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Review;
use App\Models\SubscriptionPlan;
use App\Support\ProviderVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ProviderDashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $provider = $user->serviceProvider;

        if (! $provider) {
            return response()->json([
                'success' => false,
                'message' => 'Provider profile not found. Complete onboarding.',
                'errors' => (object) [],
            ], 403);
        }

        $stats = $this->calculateStats($provider);

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

        $recentReviews = Review::where('service_provider_id', $provider->id)
            ->approved()
            ->with(['user:id,first_name,last_name,avatar'])
            ->latest()
            ->limit(3)
            ->get();

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

        $unreadNotificationsCount = 0;
        if (Schema::hasTable('notifications')) {
            $unreadNotificationsCount = (int) $user->unreadNotifications()->count();
        }

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'stats' => $stats,
                'recent_leads' => $recentLeads,
                'recent_reviews' => $recentReviews,
                'leads_chart_data' => $leadsChartData,
                'unread_notifications_count' => $unreadNotificationsCount,
                'subscription_checkout_configured' => $isSubscriptionCheckoutConfigured,
                'provider' => $provider->only([
                    'id', 'slug', 'business_name', 'average_rating', 'total_reviews',
                    'background_check_status', 'is_featured', 'profile_views',
                    'subscription_plan', 'subscription_expires_at', 'stripe_subscription_status',
                ]) + [
                    'requires_background_check' => ProviderVerification::requiresBackgroundCheck($provider),
                    'requires_certificate_upload' => ProviderVerification::requiresCertificateUpload($provider),
                    'needs_certificate_upload' => ProviderVerification::needsCertificateUpload($provider),
                ],
            ],
        ]);
    }

    protected function calculateStats($provider): array
    {
        $now = now();
        $thirtyDaysAgo = $now->copy()->subDays(30);
        $sixtyDaysAgo = $now->copy()->subDays(60);

        $totalLeads = Lead::where('service_provider_id', $provider->id)->count();
        $newLeads = Lead::where('service_provider_id', $provider->id)->new()->count();
        $openLeads = Lead::where('service_provider_id', $provider->id)->open()->count();
        $convertedLeads = Lead::where('service_provider_id', $provider->id)
            ->where('status', LeadStatus::CONVERTED)
            ->count();
        $conversionRate = $totalLeads > 0 ? round(($convertedLeads / $totalLeads) * 100, 1) : 0;
        $profileViews = $provider->profile_views ?? 0;

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
