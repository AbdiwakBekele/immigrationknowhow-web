<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\ReviewVote;
use App\Models\ServiceProvider;
use App\Notifications\NewReviewNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    public function create(ServiceProvider $provider): Response
    {
        // Check if user can review this provider
        $user = auth()->user();
        
        // Must have had at least one converted lead with this provider
        $canReview = $user->leads()
            ->where('service_provider_id', $provider->id)
            ->where('status', 'converted')
            ->exists();

        if (!$canReview) {
            return redirect()->route('marketplace.show', $provider)
                ->with('error', 'You must have used this provider\'s services before leaving a review.');
        }

        // Check if already reviewed
        $existingReview = Review::where('user_id', $user->id)
            ->where('service_provider_id', $provider->id)
            ->first();

        return Inertia::render('Reviews/Create', [
            'provider' => $provider->only(['id', 'slug', 'business_name', 'primary_service_type']),
            'existingReview' => $existingReview,
        ]);
    }

    public function store(Request $request, ServiceProvider $provider): RedirectResponse
    {
        $user = auth()->user();

        // Validate user can review
        $hasUsedService = $user->leads()
            ->where('service_provider_id', $provider->id)
            ->where('status', 'converted')
            ->exists();

        if (!$hasUsedService) {
            return back()->withErrors(['error' => 'You must have used this provider\'s services to leave a review.']);
        }

        // Check for existing review
        $existingReview = Review::where('user_id', $user->id)
            ->where('service_provider_id', $provider->id)
            ->first();

        $validated = $request->validate([
            'overall_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'communication_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'expertise_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'value_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'comment' => ['required', 'string', 'min:20', 'max:2000'],
            'would_recommend' => ['boolean'],
        ]);

        $validated['user_id'] = $user->id;
        $validated['service_provider_id'] = $provider->id;
        $validated['uuid'] = Str::uuid();

        if ($existingReview) {
            $existingReview->update($validated);
            $review = $existingReview;
            $message = 'Review updated successfully.';
        } else {
            $review = Review::create($validated);
            $message = 'Review submitted successfully.';

            // Notify provider
            $provider->user->notify(new NewReviewNotification($review));
        }

        // Update provider's average rating
        $provider->updateRatingStats();

        return redirect()->route('marketplace.show', $provider)
            ->with('success', $message);
    }

    public function markHelpful(Review $review): RedirectResponse
    {
        $user = auth()->user();

        // Can't vote on own review
        if ($review->user_id === $user->id) {
            return back()->withErrors(['error' => 'You cannot vote on your own review.']);
        }

        // Toggle vote
        $existingVote = ReviewVote::where('review_id', $review->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingVote) {
            $existingVote->delete();
            $review->decrement('helpful_votes');
        } else {
            ReviewVote::create([
                'review_id' => $review->id,
                'user_id' => $user->id,
                'is_helpful' => true,
            ]);
            $review->increment('helpful_votes');
        }

        return back();
    }

    public function index(): Response
    {
        $user = auth()->user();

        $reviews = Review::where('user_id', $user->id)
            ->with('serviceProvider:id,slug,business_name,primary_service_type')
            ->latest()
            ->paginate(10);

        return Inertia::render('User/Reviews/Index', [
            'reviews' => $reviews,
        ]);
    }

    public function edit(Review $review): Response
    {
        $this->authorize('update', $review);

        return Inertia::render('Reviews/Edit', [
            'review' => $review->load('serviceProvider:id,slug,business_name'),
        ]);
    }

    public function update(Request $request, Review $review): RedirectResponse
    {
        $this->authorize('update', $review);

        $validated = $request->validate([
            'overall_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'communication_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'expertise_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'value_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'comment' => ['required', 'string', 'min:20', 'max:2000'],
            'would_recommend' => ['boolean'],
        ]);

        $review->update($validated);

        // Update provider's average rating
        $review->serviceProvider->updateRatingStats();

        return redirect()->route('marketplace.show', $review->serviceProvider)
            ->with('success', 'Review updated successfully.');
    }

    public function destroy(Review $review): RedirectResponse
    {
        $this->authorize('delete', $review);

        $provider = $review->serviceProvider;
        $review->delete();

        // Update provider's average rating
        $provider->updateRatingStats();

        return back()->with('success', 'Review deleted.');
    }
}
