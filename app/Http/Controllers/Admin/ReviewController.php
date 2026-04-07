<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Review::query()
            ->with([
                'user:id,first_name,last_name,email,avatar',
                'serviceProvider:id,business_name,slug,primary_service_type',
            ]);

        // Filter by flagged
        if ($request->filled('flagged')) {
            $query->where('is_flagged', $request->flagged === 'yes');
        }

        // Filter by rating
        if ($request->filled('rating')) {
            $query->where('overall_rating', $request->rating);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('comment', 'like', "%{$search}%")
                    ->orWhereHas('user', fn($uq) => $uq->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"))
                    ->orWhereHas('serviceProvider', fn($pq) => $pq->where('business_name', 'like', "%{$search}%"));
            });
        }

        $query->orderByDesc('created_at');

        $reviews = $query->paginate(20)->withQueryString();

        // Stats
        $stats = [
            'total' => Review::count(),
            'flagged' => Review::where('is_flagged', true)->count(),
            'today' => Review::whereDate('created_at', today())->count(),
        ];

        return Inertia::render('Admin/Reviews/Index', [
            'reviews' => $reviews,
            'filters' => $request->only(['flagged', 'rating', 'search']),
            'stats' => $stats,
        ]);
    }

    public function show(Review $review): Response
    {
        $review->load([
            'user',
            'serviceProvider.user',
        ]);

        return Inertia::render('Admin/Reviews/Show', [
            'review' => $review,
        ]);
    }

    public function approve(Review $review): RedirectResponse
    {
        $review->update([
            'is_flagged' => false,
            'flag_reason' => null,
            'flag_details' => null,
            'flagged_at' => null,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Review approved.');
    }

    public function reject(Request $request, Review $review): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        // Hide the review
        $review->update([
            'is_hidden' => true,
            'hidden_reason' => $validated['reason'] ?? 'Violated community guidelines',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        // Update provider's rating stats
        $review->serviceProvider->updateRatingStats();

        return back()->with('success', 'Review hidden.');
    }

    public function restore(Review $review): RedirectResponse
    {
        $review->update([
            'is_hidden' => false,
            'hidden_reason' => null,
        ]);

        // Update provider's rating stats
        $review->serviceProvider->updateRatingStats();

        return back()->with('success', 'Review restored.');
    }

    public function destroy(Review $review): RedirectResponse
    {
        $provider = $review->serviceProvider;
        $review->delete();

        // Update provider's rating stats
        $provider->updateRatingStats();

        return redirect()->route('admin.reviews.index')
            ->with('success', 'Review deleted permanently.');
    }

    public function toggleFeatured(Review $review): RedirectResponse
    {
        $review->update(['is_featured' => !$review->is_featured]);

        return back()->with('success', $review->is_featured 
            ? 'Review is now featured on homepage.' 
            : 'Review removed from featured.');
    }
}
