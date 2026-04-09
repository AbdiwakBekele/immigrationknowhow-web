<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BackgroundCheckStatus;
use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\BackgroundCheck;
use App\Models\Lead;
use App\Models\LibraryItem;
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
        // User stats
        $userStats = [
            'total' => User::count(),
            'this_month' => User::whereMonth('created_at', now()->month)->count(),
            'verified' => User::verified()->count(),
            'active' => User::where('last_login_at', '>=', now()->subDays(30))->count(),
        ];

        // Provider stats
        $providerStats = [
            'total' => ServiceProvider::count(),
            'verified' => ServiceProvider::where('background_check_status', 'clear')->count(),
            'pending_verification' => ServiceProvider::whereIn('background_check_status', ['invited', 'completed'])->count(),
            'active' => ServiceProvider::active()->acceptingClients()->count(),
        ];

        // Background check stats
        $backgroundCheckStats = [
            'pending' => BackgroundCheck::whereIn('status', [BackgroundCheckStatus::PENDING, BackgroundCheckStatus::INVITED])->count(),
            'in_progress' => BackgroundCheck::where('status', BackgroundCheckStatus::COMPLETED)->count(),
            'cleared' => BackgroundCheck::where('status', BackgroundCheckStatus::CLEAR)->count(),
            'needs_review' => BackgroundCheck::whereIn('status', [BackgroundCheckStatus::CONSIDER, BackgroundCheckStatus::SUSPENDED])->count(),
        ];

        // Lead stats
        $leadStats = [
            'total' => Lead::count(),
            'this_month' => Lead::whereMonth('created_at', now()->month)->count(),
            'open' => Lead::open()->count(),
            'converted' => Lead::where('status', LeadStatus::CONVERTED)->count(),
            'conversion_rate' => $this->calculateConversionRate(),
        ];

        // Review stats
        $reviewStats = [
            'total' => Review::count(),
            'pending_moderation' => Review::where('is_approved', false)->count(),
            'average_rating' => round(Review::avg('rating'), 1),
        ];

        // Library stats
        $libraryStats = [
            'total_items' => LibraryItem::count(),
            'ebooks' => LibraryItem::where('type', 'ebook')->count(),
            'audiobooks' => LibraryItem::where('type', 'audiobook')->count(),
            'total_downloads' => LibraryItem::sum('download_count'),
        ];

        // Recent activity
        $recentUsers = User::latest()->limit(5)->get(['id', 'first_name', 'last_name', 'email', 'created_at']);
        $recentLeads = Lead::with(['user:id,first_name,last_name', 'serviceProvider:id,business_name'])
            ->latest()
            ->limit(5)
            ->get();
        $pendingBackgroundChecks = BackgroundCheck::with(['serviceProvider.user:id,first_name,last_name,email'])
            ->whereIn('status', [BackgroundCheckStatus::INVITED, BackgroundCheckStatus::COMPLETED, BackgroundCheckStatus::CONSIDER])
            ->latest()
            ->limit(5)
            ->get();

        // Chart data - leads by day for last 30 days
        $leadsChartData = Lead::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn($item) => [
                'date' => $item->date,
                'count' => $item->count,
            ]);

        // Service type distribution
        $serviceTypeDistribution = ServiceProvider::active()
            ->get()
            ->flatMap(fn($p) => $p->service_types)
            ->countBy()
            ->map(fn($count, $type) => [
                'type' => \App\Enums\ServiceType::tryFrom($type)?->label() ?? $type,
                'count' => $count,
            ])
            ->values()
            ->sortByDesc('count')
            ->take(10);

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
            'leadsChartData' => $leadsChartData,
            'serviceTypeDistribution' => $serviceTypeDistribution,
        ]);
    }

    protected function calculateConversionRate(): float
    {
        $total = Lead::count();
        if ($total === 0) return 0;

        $converted = Lead::where('status', LeadStatus::CONVERTED)->count();
        return round(($converted / $total) * 100, 1);
    }
}
