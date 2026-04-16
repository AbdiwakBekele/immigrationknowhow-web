<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BackgroundCheckStatus;
use App\Enums\LeadStatus;
use App\Enums\ServiceType;
use App\Http\Controllers\Controller;
use App\Models\BackgroundCheck;
use App\Models\Lead;
use App\Models\LibraryItem;
use App\Models\LibraryUserAccess;
use App\Models\Review;
use App\Models\ServiceProvider;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $today = now()->startOfDay();
        $monthStart = now()->startOfMonth();

        $userStats = [
            'total' => User::count(),
            'this_month' => User::where('created_at', '>=', $monthStart)->count(),
            'today' => User::where('created_at', '>=', $today)->count(),
            'verified' => User::verified()->count(),
            'active' => User::where('last_login_at', '>=', now()->subDays(30))->count(),
        ];

        $providerStats = [
            'total' => ServiceProvider::count(),
            'today' => ServiceProvider::where('created_at', '>=', $today)->count(),
            'verified' => BackgroundCheck::where('status', BackgroundCheckStatus::CLEAR)
                ->distinct('service_provider_id')
                ->count('service_provider_id'),
            'pending_verification' => BackgroundCheck::whereIn('status', [
                BackgroundCheckStatus::INVITED,
                BackgroundCheckStatus::COMPLETED,
                BackgroundCheckStatus::PENDING,
                BackgroundCheckStatus::CONSIDER,
            ])->distinct('service_provider_id')->count('service_provider_id'),
            'active' => ServiceProvider::active()->acceptingClients()->count(),
            'active_today' => ServiceProvider::query()
                ->where('is_active', true)
                ->where('accepting_clients', true)
                ->whereHas('user', function ($query) use ($today) {
                    $query->where('last_login_at', '>=', $today);
                })
                ->count(),
        ];

        $backgroundCheckStats = [
            'pending' => BackgroundCheck::whereIn('status', [
                BackgroundCheckStatus::PENDING,
                BackgroundCheckStatus::INVITED,
            ])->count(),
            'in_progress' => BackgroundCheck::where('status', BackgroundCheckStatus::COMPLETED)->count(),
            'cleared' => BackgroundCheck::where('status', BackgroundCheckStatus::CLEAR)->count(),
            'needs_review' => BackgroundCheck::whereIn('status', [
                BackgroundCheckStatus::CONSIDER,
                BackgroundCheckStatus::SUSPENDED,
            ])->count(),
        ];

        $leadStats = [
            'total' => Lead::count(),
            'this_month' => Lead::where('created_at', '>=', $monthStart)->count(),
            'today' => Lead::where('created_at', '>=', $today)->count(),
            'open' => Lead::open()->count(),
            'converted' => Lead::where('status', LeadStatus::CONVERTED)->count(),
            'conversion_rate' => $this->calculateConversionRate(),
        ];

        $reviewStats = [
            'total' => Review::count(),
            'pending_moderation' => Review::where('is_approved', false)->count(),
            'average_rating' => round((float) Review::avg('rating'), 1),
        ];

        $libraryStats = [
            'total_items' => LibraryItem::count(),
            'ebooks' => LibraryItem::where('type', 'ebook')->count(),
            'audiobooks' => LibraryItem::where('type', 'audiobook')->count(),
            'total_downloads' => LibraryItem::sum('download_count'),
            'ebooks_sold_today' => LibraryUserAccess::query()
                ->whereNotNull('purchased_at')
                ->where('purchased_at', '>=', $today)
                ->whereHas('libraryItem', function ($query) {
                    $query->where('type', 'ebook');
                })
                ->count(),
            'ebook_revenue_today' => (float) LibraryUserAccess::query()
                ->whereNotNull('purchased_at')
                ->where('purchased_at', '>=', $today)
                ->whereHas('libraryItem', function ($query) {
                    $query->where('type', 'ebook');
                })
                ->sum('purchase_amount'),
        ];

        $recentUsers = User::latest()
            ->limit(6)
            ->get(['id', 'first_name', 'last_name', 'email', 'avatar', 'created_at']);

        $recentLeads = Lead::with(['user:id,first_name,last_name', 'serviceProvider:id,business_name'])
            ->latest()
            ->limit(5)
            ->get();

        $pendingBackgroundChecks = BackgroundCheck::with(['serviceProvider.user:id,first_name,last_name,email,avatar'])
            ->whereIn('status', [
                BackgroundCheckStatus::INVITED,
                BackgroundCheckStatus::COMPLETED,
                BackgroundCheckStatus::CONSIDER,
                BackgroundCheckStatus::PENDING,
            ])
            ->latest()
            ->limit(6)
            ->get();

        $recentReviews = Review::with([
                'user:id,first_name,last_name',
                'serviceProvider:id,business_name',
            ])
            ->latest()
            ->limit(5)
            ->get();

        $leadsChartData = Lead::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($item) => [
                'date' => $item->date,
                'count' => $item->count,
            ]);

        $serviceTypeDistribution = ServiceProvider::active()
            ->get()
            ->flatMap(fn ($provider) => $provider->service_types)
            ->countBy()
            ->map(fn ($count, $type) => [
                'type' => ServiceType::tryFrom($type)?->label() ?? $type,
                'count' => $count,
            ])
            ->values()
            ->sortByDesc('count')
            ->take(10)
            ->values();

        return Inertia::render('Admin/Dashboard', [
            'userStats' => $userStats,
            'providerStats' => $providerStats,
            'backgroundCheckStats' => $backgroundCheckStats,
            'leadStats' => $leadStats,
            'reviewStats' => $reviewStats,
            'libraryStats' => $libraryStats,
            'recentUsers' => $recentUsers,
            'recentLeads' => $recentLeads,
            'pendingBackgroundChecks' => $pendingBackgroundChecks,
            'recentReviews' => $recentReviews,
            'leadsChartData' => $leadsChartData,
            'serviceTypeDistribution' => $serviceTypeDistribution,
        ]);
    }

    protected function calculateConversionRate(): float
    {
        $total = Lead::count();

        if ($total === 0) {
            return 0;
        }

        $converted = Lead::where('status', LeadStatus::CONVERTED)->count();

        return round(($converted / $total) * 100, 1);
    }
}