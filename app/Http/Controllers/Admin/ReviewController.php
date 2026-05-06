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
                'serviceProvider:id,business_name,slug',
            ]);

        // Filter: pending moderation = not approved (replaces legacy is_flagged)
        if ($request->filled('flagged')) {
            if ($request->flagged === 'yes') {
                $query->where('is_approved', false);
            } elseif ($request->flagged === 'no') {
                $query->where('is_approved', true);
            }
        }

        // Filter by star rating
        if ($request->filled('rating')) {
            $query->where('rating', $request->rating);
        }

        // Filter by date (today only)
        if ($request->filled('today') && $request->today === 'yes') {
            $query->whereDate('created_at', today());
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

        $reviewsByRating = collect([5, 4, 3, 2, 1])->map(function (int $rating) use ($query) {
            $items = (clone $query)
                ->where('rating', $rating)
                ->limit(100)
                ->get()
                ->map(function (Review $review) {
                    return [
                        'id' => $review->id,
                        'uuid' => $review->uuid,
                        'rating' => $review->rating,
                        'comment' => $review->comment,
                        'is_approved' => (bool) $review->is_approved,
                        'created_at' => $review->created_at,
                        'user' => [
                            'id' => $review->user?->id,
                            'name' => trim(($review->user?->first_name ?? '').' '.($review->user?->last_name ?? '')),
                            'email' => $review->user?->email,
                        ],
                        'provider' => [
                            'id' => $review->serviceProvider?->id,
                            'business_name' => $review->serviceProvider?->business_name,
                            'slug' => $review->serviceProvider?->slug,
                        ],
                    ];
                })
                ->values();

            return [
                'rating' => $rating,
                'count' => $items->count(),
                'reviews' => $items,
            ];
        })->values();

        $reviews = $query->paginate(20)->withQueryString();

        // Stats (no is_flagged column — use unapproved as "needs attention")
        $stats = [
            'total' => Review::count(),
            'pending' => Review::where('is_approved', false)->count(),
            'today' => Review::whereDate('created_at', today())->count(),
            'average_rating' => round((float) Review::avg('rating'), 1),
        ];

        return Inertia::render('Admin/Reviews/Index', [
            'reviews' => $reviews,
            'filters' => $request->only(['flagged', 'rating', 'today', 'search']),
            'stats' => $stats,
            'reviewsByRating' => $reviewsByRating,
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
            'is_approved' => true,
            'moderation_notes' => null,
        ]);

        $review->serviceProvider->updateRating();

        return back()->with('success', 'Review approved.');
    }

    public function reject(Request $request, Review $review): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $review->update([
            'is_approved' => false,
            'moderation_notes' => $validated['reason'] ?? 'Violated community guidelines',
        ]);

        $review->serviceProvider->updateRating();

        return back()->with('success', 'Review hidden.');
    }

    public function restore(Review $review): RedirectResponse
    {
        $review->update([
            'is_approved' => true,
            'moderation_notes' => null,
        ]);

        $review->serviceProvider->updateRating();

        return back()->with('success', 'Review restored.');
    }

    public function destroy(Review $review): RedirectResponse
    {
        $provider = $review->serviceProvider;
        $review->delete();

        $provider->updateRating();

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
