<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileProviderReviewsController extends Controller
{
    /**
     * Paginated approved reviews left for the authenticated service provider.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $provider = $user->serviceProvider;

        if (! $provider) {
            return response()->json([
                'success' => false,
                'message' => 'Provider profile not found. Complete onboarding.',
                'errors' => (object) [],
            ], 403);
        }

        $perPage = min(max((int) $request->query('per_page', 15), 1), 50);

        $reviews = Review::query()
            ->where('service_provider_id', $provider->id)
            ->approved()
            ->with(['user:id,first_name,last_name,avatar'])
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'reviews' => $reviews,
            ],
        ]);
    }
}
