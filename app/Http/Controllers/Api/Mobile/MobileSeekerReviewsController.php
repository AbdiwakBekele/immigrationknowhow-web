<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\ReviewVote;
use App\Models\ServiceProvider;
use App\Notifications\NewReviewNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MobileSeekerReviewsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $reviews = Review::where('user_id', $request->user()->id)
            ->with('serviceProvider:id,slug,business_name,service_types')
            ->latest()
            ->paginate(12);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'reviews' => $reviews,
            ],
        ]);
    }

    public function store(Request $request, ServiceProvider $provider): JsonResponse
    {
        $user = $request->user();

        $hasUsedService = $user->leads()
            ->where('service_provider_id', $provider->id)
            ->where('status', LeadStatus::CONVERTED)
            ->exists();

        if (! $hasUsedService) {
            return response()->json([
                'success' => false,
                'message' => 'You must have used this provider\'s services to leave a review.',
                'errors' => (object) [],
            ], 422);
        }

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
        $validated['uuid'] = (string) Str::uuid();
        $validated['rating'] = $validated['overall_rating'];
        unset($validated['overall_rating'], $validated['would_recommend']);

        if ($existingReview) {
            $existingReview->update($validated);
            $review = $existingReview;
            $message = 'Review updated successfully.';
        } else {
            $review = Review::create($validated);
            $message = 'Review submitted successfully.';
            $provider->user->notify(new NewReviewNotification($review));
        }

        $provider->updateRating();

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'review' => $review->load('serviceProvider:id,slug,business_name'),
            ],
        ], $existingReview ? 200 : 201);
    }

    public function helpful(Request $request, Review $review): JsonResponse
    {
        $user = $request->user();

        if ($review->user_id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot vote on your own review.',
                'errors' => (object) [],
            ], 422);
        }

        $existingVote = ReviewVote::where('review_id', $review->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingVote) {
            $existingVote->delete();
            $review->decrement('helpful_count');
            $voted = false;
        } else {
            ReviewVote::create([
                'review_id' => $review->id,
                'user_id' => $user->id,
                'is_helpful' => true,
            ]);
            $review->increment('helpful_count');
            $voted = true;
        }

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'marked_helpful' => $voted,
                'helpful_count' => $review->fresh()->helpful_count,
            ],
        ]);
    }

    public function update(Request $request, Review $review): JsonResponse
    {
        abort_unless($review->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'overall_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'communication_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'expertise_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'value_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'comment' => ['required', 'string', 'min:20', 'max:2000'],
            'would_recommend' => ['boolean'],
        ]);

        $validated['rating'] = $validated['overall_rating'];
        unset($validated['overall_rating'], $validated['would_recommend']);

        $review->update($validated);
        $review->serviceProvider->updateRating();

        return response()->json([
            'success' => true,
            'message' => 'Review updated.',
            'data' => [
                'review' => $review->fresh()->load('serviceProvider:id,slug,business_name'),
            ],
        ]);
    }

    public function destroy(Request $request, Review $review): JsonResponse
    {
        abort_unless($review->user_id === $request->user()->id, 403);

        $provider = $review->serviceProvider;
        $review->delete();
        $provider->updateRating();

        return response()->json([
            'success' => true,
            'message' => 'Review deleted.',
            'data' => (object) [],
        ]);
    }
}
