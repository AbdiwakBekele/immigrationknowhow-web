<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Review;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderAnalyticsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $provider = $request->user()->serviceProvider;
        abort_unless($provider, 403);

        $period = (int) ($request->query('period', 30));
        $period = $period > 0 ? min($period, 365) : 30;
        $startDate = now()->subDays($period);

        $leadStats = $this->getLeadStats((int) $provider->id, $startDate);
        $leadTrends = $this->getLeadTrends((int) $provider->id, $startDate);
        $reviewStats = $this->getReviewStats((int) $provider->id, $startDate);
        $profileViews = $this->getProfileViews((int) $provider->id, $startDate);
        $avgResponseTime = $this->getAverageResponseTime((int) $provider->id, $startDate);
        $conversionFunnel = $this->getConversionFunnel((int) $provider->id, $startDate);
        $topServices = $this->getTopServices((int) $provider->id, $startDate);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
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
            ],
        ]);
    }

    private function getLeadStats(int $providerId, Carbon $startDate): array
    {
        $current = Lead::where('service_provider_id', $providerId)
            ->where('created_at', '>=', $startDate)
            ->count();

        $window = $startDate->diffInDays(now());
        $previous = Lead::where('service_provider_id', $providerId)
            ->whereBetween('created_at', [
                $startDate->copy()->subDays($window),
                $startDate,
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
            ->map(fn ($item) => [
                'date' => Carbon::parse($item->date)->format('M d'),
                'count' => (int) $item->count,
            ])
            ->toArray();
    }

    private function getReviewStats(int $providerId, Carbon $startDate): array
    {
        $reviews = Review::where('service_provider_id', $providerId)
            ->where('created_at', '>=', $startDate);

        return [
            'total' => $reviews->count(),
            'averageRating' => round($reviews->avg('rating') ?? 0, 1),
            'fiveStars' => $reviews->clone()->where('rating', 5)->count(),
            'needsResponse' => $reviews->clone()->whereNull('provider_response')->count(),
        ];
    }

    private function getProfileViews(int $providerId, Carbon $startDate): array
    {
        // Not tracked in mobile yet.
        return [
            'total' => 0,
            'change' => 0,
            'unique' => 0,
        ];
    }

    private function getAverageResponseTime(int $providerId, Carbon $startDate): string
    {
        $avg = Lead::where('service_provider_id', $providerId)
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('responded_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, responded_at)) as avg_hours')
            ->value('avg_hours');

        if (! $avg) {
            return 'N/A';
        }

        if ($avg < 1) {
            return '< 1 hour';
        }
        if ($avg < 24) {
            return round($avg).' hours';
        }

        return round($avg / 24, 1).' days';
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
            ->whereNotNull('responded_at')
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
            ->map(fn ($item) => [
                'service' => $item->service_type,
                'label' => $item->service_type,
                'count' => (int) $item->count,
            ])
            ->toArray();
    }
}

