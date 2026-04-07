<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Review;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function index(Request $request): Response
    {
        $provider = auth()->user()->serviceProvider;
        $period = $request->get('period', '30'); // days
        $startDate = now()->subDays((int) $period);

        // Lead stats
        $leadStats = $this->getLeadStats($provider->id, $startDate);

        // Lead trends (daily counts)
        $leadTrends = $this->getLeadTrends($provider->id, $startDate);

        // Review stats
        $reviewStats = $this->getReviewStats($provider->id, $startDate);

        // Profile views (if tracked)
        $profileViews = $this->getProfileViews($provider->id, $startDate);

        // Response time average
        $avgResponseTime = $this->getAverageResponseTime($provider->id, $startDate);

        // Conversion funnel
        $conversionFunnel = $this->getConversionFunnel($provider->id, $startDate);

        // Top performing services
        $topServices = $this->getTopServices($provider->id, $startDate);

        return Inertia::render('Provider/Analytics/Index', [
            'period' => $period,
            'stats' => [
                'leads' => $leadStats,
                'reviews' => $reviewStats,
                'profileViews' => $profileViews,
                'avgResponseTime' => $avgResponseTime,
            ],
            'trends' => [
                'leads' => $leadTrends,
            ],
            'conversionFunnel' => $conversionFunnel,
            'topServices' => $topServices,
        ]);
    }

    private function getLeadStats(int $providerId, Carbon $startDate): array
    {
        $current = Lead::where('service_provider_id', $providerId)
            ->where('created_at', '>=', $startDate)
            ->count();

        $previous = Lead::where('service_provider_id', $providerId)
            ->whereBetween('created_at', [
                $startDate->copy()->subDays($startDate->diffInDays(now())),
                $startDate
            ])
            ->count();

        $change = $previous > 0 
            ? round((($current - $previous) / $previous) * 100, 1) 
            : ($current > 0 ? 100 : 0);

        $converted = Lead::where('service_provider_id', $providerId)
            ->where('created_at', '>=', $startDate)
            ->where('status', 'converted')
            ->count();

        return [
            'total' => $current,
            'change' => $change,
            'converted' => $converted,
            'conversionRate' => $current > 0 ? round(($converted / $current) * 100, 1) : 0,
        ];
    }

    private function getLeadTrends(int $providerId, Carbon $startDate): array
    {
        return Lead::where('service_provider_id', $providerId)
            ->where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn($item) => [
                'date' => Carbon::parse($item->date)->format('M d'),
                'count' => $item->count,
            ])
            ->toArray();
    }

    private function getReviewStats(int $providerId, Carbon $startDate): array
    {
        $reviews = Review::where('service_provider_id', $providerId)
            ->where('created_at', '>=', $startDate);

        return [
            'total' => $reviews->count(),
            'averageRating' => round($reviews->avg('overall_rating') ?? 0, 1),
            'fiveStars' => $reviews->clone()->where('overall_rating', 5)->count(),
            'needsResponse' => $reviews->clone()->whereNull('provider_response')->count(),
        ];
    }

    private function getProfileViews(int $providerId, Carbon $startDate): array
    {
        // This would typically come from a page_views or analytics table
        // For now, return placeholder data
        return [
            'total' => 0,
            'change' => 0,
            'unique' => 0,
        ];
    }

    private function getAverageResponseTime(int $providerId, Carbon $startDate): string
    {
        // Calculate average time between lead creation and first message
        $avg = Lead::where('service_provider_id', $providerId)
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('contacted_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, contacted_at)) as avg_hours')
            ->value('avg_hours');

        if (!$avg) {
            return 'N/A';
        }

        if ($avg < 1) {
            return '< 1 hour';
        } elseif ($avg < 24) {
            return round($avg) . ' hours';
        } else {
            return round($avg / 24, 1) . ' days';
        }
    }

    private function getConversionFunnel(int $providerId, Carbon $startDate): array
    {
        $total = Lead::where('service_provider_id', $providerId)
            ->where('created_at', '>=', $startDate)
            ->count();

        if ($total === 0) {
            return [
                ['stage' => 'Received', 'count' => 0, 'percentage' => 0],
                ['stage' => 'Contacted', 'count' => 0, 'percentage' => 0],
                ['stage' => 'In Progress', 'count' => 0, 'percentage' => 0],
                ['stage' => 'Converted', 'count' => 0, 'percentage' => 0],
            ];
        }

        $contacted = Lead::where('service_provider_id', $providerId)
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('contacted_at')
            ->count();

        $inProgress = Lead::where('service_provider_id', $providerId)
            ->where('created_at', '>=', $startDate)
            ->whereIn('status', ['in_progress', 'converted'])
            ->count();

        $converted = Lead::where('service_provider_id', $providerId)
            ->where('created_at', '>=', $startDate)
            ->where('status', 'converted')
            ->count();

        return [
            ['stage' => 'Received', 'count' => $total, 'percentage' => 100],
            ['stage' => 'Contacted', 'count' => $contacted, 'percentage' => round(($contacted / $total) * 100)],
            ['stage' => 'In Progress', 'count' => $inProgress, 'percentage' => round(($inProgress / $total) * 100)],
            ['stage' => 'Converted', 'count' => $converted, 'percentage' => round(($converted / $total) * 100)],
        ];
    }

    private function getTopServices(int $providerId, Carbon $startDate): array
    {
        return Lead::where('service_provider_id', $providerId)
            ->where('created_at', '>=', $startDate)
            ->selectRaw('service_type, COUNT(*) as count')
            ->groupBy('service_type')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(fn($item) => [
                'service' => $item->service_type,
                'label' => $item->service_type, // Would use enum label
                'count' => $item->count,
            ])
            ->toArray();
    }
}
