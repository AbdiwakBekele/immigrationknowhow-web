<?php

namespace App\Http\Controllers\Provider;

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
        $provider = auth()->user()->serviceProvider;

        $query = Review::where('service_provider_id', $provider->id)
            ->with('user:id,first_name,last_name,avatar');

        // Filter by rating
        if ($request->filled('rating')) {
            $query->where('rating', $request->rating);
        }

        // Filter by response status
        if ($request->filled('responded')) {
            if ($request->responded === 'yes') {
                $query->whereNotNull('provider_response');
            } else {
                $query->whereNull('provider_response');
            }
        }

        // Sort
        $sortBy = $request->get('sort', 'newest');
        match ($sortBy) {
            'oldest' => $query->oldest(),
            'highest' => $query->orderByDesc('rating'),
            'lowest' => $query->orderBy('rating'),
            default => $query->latest(),
        };

        $reviews = $query->paginate(10);

        // Calculate stats
        $stats = [
            'total' => Review::where('service_provider_id', $provider->id)->count(),
            'average' => $provider->average_rating ?? 0,
            'awaiting_response' => Review::where('service_provider_id', $provider->id)
                ->whereNull('provider_response')
                ->count(),
            'distribution' => [
                5 => Review::where('service_provider_id', $provider->id)->where('rating', 5)->count(),
                4 => Review::where('service_provider_id', $provider->id)->where('rating', 4)->count(),
                3 => Review::where('service_provider_id', $provider->id)->where('rating', 3)->count(),
                2 => Review::where('service_provider_id', $provider->id)->where('rating', 2)->count(),
                1 => Review::where('service_provider_id', $provider->id)->where('rating', 1)->count(),
            ],
        ];

        return Inertia::render('Provider/Reviews/Index', [
            'reviews' => $reviews,
            'stats' => $stats,
            'filters' => $request->only(['rating', 'responded', 'sort']),
        ]);
    }

    public function respond(Request $request, Review $review): RedirectResponse
    {
        // Ensure this review belongs to the provider
        $provider = auth()->user()->serviceProvider;
        
        if ($review->service_provider_id !== $provider->id) {
            abort(403);
        }

        $validated = $request->validate([
            'response' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $review->update([
            'provider_response' => $validated['response'],
            'provider_responded_at' => now(),
        ]);

        return back()->with('success', 'Response posted successfully.');
    }

    public function updateResponse(Request $request, Review $review): RedirectResponse
    {
        $provider = auth()->user()->serviceProvider;
        
        if ($review->service_provider_id !== $provider->id) {
            abort(403);
        }

        $validated = $request->validate([
            'response' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $review->update([
            'provider_response' => $validated['response'],
        ]);

        return back()->with('success', 'Response updated.');
    }

    public function deleteResponse(Review $review): RedirectResponse
    {
        $provider = auth()->user()->serviceProvider;
        
        if ($review->service_provider_id !== $provider->id) {
            abort(403);
        }

        $review->update([
            'provider_response' => null,
            'provider_responded_at' => null,
        ]);

        return back()->with('success', 'Response removed.');
    }

    public function report(Request $request, Review $review): RedirectResponse
    {
        $provider = auth()->user()->serviceProvider;
        
        if ($review->service_provider_id !== $provider->id) {
            abort(403);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'in:inappropriate,fake,spam,other'],
            'details' => ['nullable', 'string', 'max:500'],
        ]);

        // Queue for admin moderation (reviews table has is_approved / moderation_notes)
        $note = 'Provider report: ' . $validated['reason'];
        if (!empty($validated['details'])) {
            $note .= ' — ' . $validated['details'];
        }

        $review->update([
            'is_approved' => false,
            'moderation_notes' => $note,
        ]);

        $review->serviceProvider->updateRating();

        return back()->with('success', 'Review has been reported and will be reviewed by our team.');
    }
}
