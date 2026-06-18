<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Api\Mobile\Concerns\DetectsMobileClient;
use App\Http\Controllers\Controller;
use App\Models\VideoEmbed;
use App\Models\VideoUserAccess;
use App\Actions\Video\FulfillVideoStripeCheckout;
use App\Support\StripeConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Stripe;

class MobileVideoController extends Controller
{
    use DetectsMobileClient;

    public function index(Request $request): JsonResponse
    {
        $query = VideoEmbed::query()->active()->ordered();

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($builder) use ($search) {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if ($request->filled('platform')) {
            $query->where('platform', $request->string('platform')->toString());
        }

        if ($request->filled('category')) {
            $query->where('category', $request->string('category')->toString());
        }

        $paginator = $query->paginate(min(32, max(4, (int) $request->query('per_page', 16))));

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'videos' => $paginator,
                'platform_options' => VideoEmbed::query()
                    ->active()
                    ->select('platform')
                    ->distinct()
                    ->orderBy('platform')
                    ->pluck('platform')
                    ->values(),
                'category_options' => VideoEmbed::query()
                    ->active()
                    ->whereNotNull('category')
                    ->where('category', '!=', '')
                    ->select('category')
                    ->distinct()
                    ->orderBy('category')
                    ->pluck('category')
                    ->values(),
            ],
        ]);
    }

    public function show(Request $request, VideoEmbed $video): JsonResponse
    {
        abort_unless($video->is_active, 404);
        $video->incrementViews();

        $requiresPaidAccess = (float) ($video->price ?? 0) > 0;
        $userAccess = $video->userAccess()->where('user_id', $request->user()->id)->first();
        $hasAccess = ! $requiresPaidAccess || (bool) $userAccess?->purchased_at;

        $streamPath = $hasAccess && $video->isUpload()
            ? '/api/mobile/videos/'.$video->slug.'/stream'
            : null;

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'video' => $video,
                'has_access' => $hasAccess,
                'requires_paid_access' => $requiresPaidAccess,
                'stream_path' => $streamPath,
                'stripe_configured' => StripeConfig::checkoutConfigured(),
                'apple_product_id' => $requiresPaidAccess ? $video->appleProductId() : null,
                'ios_requires_apple_iap' => true,
                'related' => VideoEmbed::query()
                    ->active()
                    ->where('id', '!=', $video->id)
                    ->when($video->category, fn ($q) => $q->where('category', $video->category))
                    ->ordered()
                    ->limit(8)
                    ->get(['id', 'title', 'slug', 'platform', 'thumbnail_url', 'category', 'price', 'currency']),
            ],
        ]);
    }

    public function stripeCheckout(Request $request, VideoEmbed $video): JsonResponse
    {
        abort_unless($video->is_active, 404);

        if ($blocked = $this->iosStripeCheckoutBlockedResponse(
            $request,
            'On iOS, use In-App Purchase to buy this video.',
        )) {
            return $blocked;
        }

        $price = (float) ($video->price ?? 0);
        if ($price <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'This video is already free.',
                'errors' => (object) [],
            ], 422);
        }

        if ($video->userAccess()
            ->where('user_id', $request->user()->id)
            ->whereNotNull('purchased_at')
            ->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You already have access.',
                'errors' => (object) [],
            ], 422);
        }

        if (! StripeConfig::checkoutConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Payments are not configured.',
                'errors' => (object) [],
            ], 422);
        }

        $currency = strtolower((string) ($video->currency ?? 'USD'));
        $unitAmount = (int) round($price * 100);
        if ($currency === 'usd' && $unitAmount < 50) {
            return response()->json([
                'success' => false,
                'message' => 'Price is below Stripe minimum.',
                'errors' => (object) [],
            ], 422);
        }

        $description = Str::limit(strip_tags((string) $video->description), 450);
        if ($description === '') {
            $description = 'Digital video';
        }

        try {
            Stripe::setApiKey((string) config('services.stripe.secret'));
            $session = StripeCheckoutSession::create([
                'mode' => 'payment',
                'customer_email' => $request->user()->email,
                'client_reference_id' => (string) $request->user()->id,
                'success_url' => route('mobile.video.checkout-return', [], true).'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('mobile.video.checkout-return', [], true).'?checkout=cancelled',
                'metadata' => [
                    'app' => 'video',
                    'video_id' => (string) $video->id,
                    'user_id' => (string) $request->user()->id,
                ],
                'line_items' => [[
                    'price_data' => [
                        'currency' => $currency,
                        'unit_amount' => $unitAmount,
                        'product_data' => [
                            'name' => $video->title,
                            'description' => $description,
                        ],
                    ],
                    'quantity' => 1,
                ]],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? $e->getMessage() : 'Payment could not start.',
                'errors' => (object) [],
            ], 422);
        }

        $checkoutUrl = $session->url;
        if (! is_string($checkoutUrl) || trim($checkoutUrl) === '') {
            return response()->json([
                'success' => false,
                'message' => 'Could not start checkout.',
                'errors' => (object) [],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'checkout_url' => $checkoutUrl,
            ],
        ]);
    }

    public function grantFree(Request $request, VideoEmbed $video): JsonResponse
    {
        abort_unless($video->is_active, 404);

        $price = (float) ($video->price ?? 0);
        if ($price > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Use checkout for paid videos.',
                'errors' => (object) [],
            ], 422);
        }

        $access = VideoUserAccess::query()->firstOrCreate([
            'user_id' => $request->user()->id,
            'video_embed_id' => $video->id,
        ]);

        if (! $access->purchased_at) {
            $access->update([
                'purchased_at' => now(),
                'purchase_amount' => 0,
                'purchase_currency' => $video->currency ?? 'USD',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => (object) [],
        ]);
    }

    public function confirmCheckout(Request $request, VideoEmbed $video, FulfillVideoStripeCheckout $fulfill): JsonResponse
    {
        abort_unless($video->is_active, 404);

        if ($blocked = $this->iosStripeCheckoutBlockedResponse(
            $request,
            'On iOS, use In-App Purchase to buy this video.',
        )) {
            return $blocked;
        }

        $sessionId = $request->input('session_id');
        if (! is_string($sessionId) || trim($sessionId) === '') {
            return response()->json([
                'success' => false,
                'message' => 'Missing session ID.',
                'errors' => (object) [],
            ], 422);
        }

        if (! StripeConfig::checkoutConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Payments are not configured.',
                'errors' => (object) [],
            ], 422);
        }

        try {
            Stripe::setApiKey((string) config('services.stripe.secret'));
            $session = StripeCheckoutSession::retrieve(trim($sessionId));
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Could not verify payment.',
                'errors' => (object) [],
            ], 422);
        }

        $metadataUserId = (int) ($session->metadata['user_id'] ?? 0);
        if ($metadataUserId !== (int) $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Session does not belong to this user.',
                'errors' => (object) [],
            ], 403);
        }

        $metadataVideoId = (int) ($session->metadata['video_id'] ?? 0);
        if ($metadataVideoId !== (int) $video->id) {
            return response()->json([
                'success' => false,
                'message' => 'Checkout session does not match this video.',
                'errors' => (object) [],
            ], 422);
        }

        $fulfilled = $fulfill($session);

        return response()->json([
            'success' => true,
            'message' => $fulfilled ? 'Purchase confirmed.' : 'Payment is still processing.',
            'data' => [
                'fulfilled' => $fulfilled,
            ],
        ]);
    }
}
