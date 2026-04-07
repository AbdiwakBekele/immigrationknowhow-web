<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AffiliateClick;
use App\Models\AffiliateLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AffiliateController extends Controller
{
    public function index(Request $request): Response
    {
        $query = AffiliateLink::query()
            ->withCount('clicks');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('tracking_code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('placement')) {
            $query->where('placement', $request->placement);
        }

        $query->orderByDesc('created_at');

        $links = $query->paginate(20)->withQueryString();

        // Stats
        $stats = [
            'total_links' => AffiliateLink::count(),
            'active_links' => AffiliateLink::where('is_active', true)->count(),
            'total_clicks' => AffiliateClick::count(),
            'clicks_today' => AffiliateClick::whereDate('created_at', today())->count(),
        ];

        return Inertia::render('Admin/Affiliates/Index', [
            'links' => $links,
            'filters' => $request->only(['search', 'placement']),
            'stats' => $stats,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Affiliates/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'destination_url' => ['required', 'url', 'max:2000'],
            'placement' => ['required', 'string', 'in:sidebar,footer,library,homepage,email'],
            'description' => ['nullable', 'string', 'max:500'],
            'image_url' => ['nullable', 'url', 'max:2000'],
            'is_active' => ['boolean'],
        ]);

        // Generate unique tracking code
        do {
            $trackingCode = Str::random(8);
        } while (AffiliateLink::where('tracking_code', $trackingCode)->exists());

        $validated['tracking_code'] = $trackingCode;

        AffiliateLink::create($validated);

        return redirect()->route('admin.affiliates.index')
            ->with('success', 'Affiliate link created.');
    }

    public function edit(AffiliateLink $affiliate): Response
    {
        $affiliate->loadCount('clicks');

        // Get click stats
        $clickStats = AffiliateClick::where('affiliate_link_id', $affiliate->id)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderByDesc('date')
            ->limit(30)
            ->get();

        return Inertia::render('Admin/Affiliates/Edit', [
            'link' => $affiliate,
            'clickStats' => $clickStats,
        ]);
    }

    public function update(Request $request, AffiliateLink $affiliate): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'destination_url' => ['required', 'url', 'max:2000'],
            'placement' => ['required', 'string', 'in:sidebar,footer,library,homepage,email'],
            'description' => ['nullable', 'string', 'max:500'],
            'image_url' => ['nullable', 'url', 'max:2000'],
            'is_active' => ['boolean'],
        ]);

        $affiliate->update($validated);

        return back()->with('success', 'Affiliate link updated.');
    }

    public function destroy(AffiliateLink $affiliate): RedirectResponse
    {
        $affiliate->delete();

        return redirect()->route('admin.affiliates.index')
            ->with('success', 'Affiliate link deleted.');
    }

    public function toggleActive(AffiliateLink $affiliate): RedirectResponse
    {
        $affiliate->update(['is_active' => !$affiliate->is_active]);

        return back()->with('success', $affiliate->is_active ? 'Link activated.' : 'Link deactivated.');
    }

    public function analytics(AffiliateLink $affiliate): Response
    {
        // Get detailed click analytics
        $clicks = AffiliateClick::where('affiliate_link_id', $affiliate->id)
            ->latest()
            ->paginate(50);

        $dailyStats = AffiliateClick::where('affiliate_link_id', $affiliate->id)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderByDesc('date')
            ->limit(30)
            ->get();

        return Inertia::render('Admin/Affiliates/Analytics', [
            'link' => $affiliate,
            'clicks' => $clicks,
            'dailyStats' => $dailyStats,
        ]);
    }
}
