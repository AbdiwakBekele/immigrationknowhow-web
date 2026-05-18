<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Actions\Advertiser\ApplyAdOwnerEditStatus;
use App\Actions\Advertiser\FulfillAdvertiserStripeCheckout;
use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\AdAnalyticsEvent;
use App\Models\AdPayment;
use App\Support\StripeConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Stripe;

class MobileAdsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $ads = Ad::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(fn (Ad $ad) => $this->toAdPayload($ad));

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'ads' => $ads,
                'ad_posting_price' => [
                    'amount_cents' => max(0, (int) config('ads.default_price_cents', 2500)),
                    'currency' => strtoupper((string) config('ads.currency', 'USD')),
                ],
            ],
        ]);
    }

    public function analytics(Request $request): JsonResponse
    {
        $user = $request->user();
        $ads = Ad::query()
            ->where('user_id', $user->id)
            ->latest()
            ->get()
            ->map(function (Ad $ad): array {
                $views = $ad->analyticsEvents()->where('event_type', 'view')->count();
                $clicks = $ad->analyticsEvents()->where('event_type', 'click')->count();

                return [
                    'uuid' => $ad->uuid,
                    'title' => $ad->title,
                    'status' => $ad->status,
                    'published_at' => optional($ad->published_at)?->toIso8601String(),
                    'created_at' => optional($ad->created_at)?->toIso8601String(),
                    'views' => $views,
                    'clicks' => $clicks,
                    'ctr' => $views > 0 ? round(($clicks / $views) * 100, 2) : 0.0,
                ];
            });

        $ids = Ad::query()->where('user_id', $user->id)->pluck('id')->all();
        $totalViews = empty($ids)
            ? 0
            : AdAnalyticsEvent::query()->whereIn('ad_id', $ids)->where('event_type', 'view')->count();
        $totalClicks = empty($ids)
            ? 0
            : AdAnalyticsEvent::query()->whereIn('ad_id', $ids)->where('event_type', 'click')->count();

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'summary' => [
                    'views' => $totalViews,
                    'clicks' => $totalClicks,
                    'ctr' => $totalViews > 0 ? round(($totalClicks / $totalViews) * 100, 2) : 0.0,
                ],
                'ads' => $ads,
            ],
        ]);
    }

    public function show(Request $request, Ad $ad): JsonResponse
    {
        $this->authorizeAd($request, $ad);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'ad' => $this->toAdPayload($ad),
                'public_url' => $ad->isPubliclyVisible() ? url('/sponsored/'.$ad->uuid) : null,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateAd($request);
        $priceCents = max(0, (int) config('ads.default_price_cents', 2500));
        $imageUrl = $this->resolveImageUrl($request, $validated);
        $requireApproval = (bool) config('ads.require_admin_approval', true);

        if ($priceCents > 0) {
            $status = 'pending_payment';
            $paidAt = null;
            $publishedAt = null;
        } elseif ($requireApproval) {
            $status = 'pending_approval';
            $paidAt = now();
            $publishedAt = null;
        } else {
            $status = 'published';
            $paidAt = now();
            $publishedAt = now();
        }

        $ad = Ad::query()->create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'cta_url' => $validated['cta_url'],
            'image_url' => $imageUrl,
            'status' => $status,
            'price_cents' => $priceCents,
            'currency' => strtoupper((string) config('ads.currency', 'USD')),
            'paid_at' => $paidAt,
            'published_at' => $publishedAt,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ad created.',
            'data' => [
                'ad' => $this->toAdPayload($ad),
            ],
        ], 201);
    }

    public function update(Request $request, Ad $ad, ApplyAdOwnerEditStatus $applyEditStatus): JsonResponse
    {
        $this->authorizeAd($request, $ad);
        $previousStatus = (string) $ad->status;
        $validated = $this->validateAd($request, false);
        $imageUrl = $this->resolveImageUrl($request, $validated, $ad->image_url);

        $ad->update([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'cta_url' => $validated['cta_url'],
            'image_url' => $imageUrl,
        ]);

        $ad = $applyEditStatus($ad->fresh(), $previousStatus);

        $message = in_array($previousStatus, ['published', 'suspended'], true) && $ad->status === 'pending_approval'
            ? 'Ad updated and submitted for administrator approval again.'
            : 'Ad updated.';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'ad' => $this->toAdPayload($ad),
            ],
        ]);
    }

    public function destroy(Request $request, Ad $ad): JsonResponse
    {
        $this->authorizeAd($request, $ad);
        $ad->delete();

        return response()->json([
            'success' => true,
            'message' => 'Ad deleted.',
            'data' => (object) [],
        ]);
    }

    public function resubmit(Request $request, Ad $ad): JsonResponse
    {
        $this->authorizeAd($request, $ad);
        abort_unless($ad->status === 'rejected', 422);

        $ad->update([
            'status' => 'pending_approval',
            'meta' => array_merge($ad->meta ?? [], [
                'resubmitted_at' => now()->toIso8601String(),
            ]),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ad resubmitted for review.',
            'data' => [
                'ad' => $this->toAdPayload($ad->fresh()),
            ],
        ]);
    }

    public function checkout(Request $request, Ad $ad): JsonResponse
    {
        $this->authorizeAd($request, $ad);

        if ($ad->isSuspended()) {
            return response()->json(['success' => false, 'message' => 'This ad was suspended by an administrator.', 'errors' => (object) []], 422);
        }
        if ($ad->status === 'published') {
            return response()->json(['success' => false, 'message' => 'Already published.', 'errors' => (object) []], 422);
        }
        if ($ad->status === 'pending_approval') {
            return response()->json(['success' => false, 'message' => 'Awaiting approval.', 'errors' => (object) []], 422);
        }
        if ($ad->status !== 'pending_payment') {
            return response()->json(['success' => false, 'message' => 'Checkout not available.', 'errors' => (object) []], 422);
        }
        if (! StripeConfig::checkoutConfigured()) {
            return response()->json(['success' => false, 'message' => 'Stripe not configured.', 'errors' => (object) []], 422);
        }

        $amountCents = max(0, (int) $ad->price_cents);
        $currency = strtolower($this->defaultCurrency());
        if ($currency === 'usd' && $amountCents < 50) {
            return response()->json(['success' => false, 'message' => 'Below Stripe minimum.', 'errors' => (object) []], 422);
        }

        try {
            Stripe::setApiKey((string) config('services.stripe.secret'));
            $session = StripeCheckoutSession::create([
                'mode' => 'payment',
                'customer_email' => $request->user()->email,
                'client_reference_id' => (string) $request->user()->id,
                'success_url' => route($this->adsRoutePrefix($request).'.purchase.return', [], true).'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route($this->adsRoutePrefix($request).'.purchase.cancel', $ad, true),
                'metadata' => [
                    'app' => 'advertiser_ad',
                    'ad_id' => (string) $ad->id,
                    'user_id' => (string) $request->user()->id,
                ],
                'line_items' => [[
                    'price_data' => [
                        'currency' => $currency,
                        'unit_amount' => $amountCents,
                        'product_data' => [
                            'name' => 'Ad publication fee',
                            'description' => 'One-time payment to publish ad: '.$ad->title,
                        ],
                    ],
                    'quantity' => 1,
                ]],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? $e->getMessage() : 'Checkout failed.',
                'errors' => (object) [],
            ], 422);
        }

        AdPayment::query()->create([
            'ad_id' => $ad->id,
            'user_id' => $request->user()->id,
            'amount_cents' => $amountCents,
            'currency' => strtoupper($currency),
            'status' => 'pending',
            'stripe_checkout_session_id' => $session->id,
            'meta' => ['source' => 'checkout_start_mobile'],
        ]);

        $ad->update([
            'last_paid_checkout_started_at' => now(),
            'last_paid_checkout_session_id' => $session->id,
        ]);

        $checkoutUrl = $session->url;
        if (! is_string($checkoutUrl) || trim($checkoutUrl) === '') {
            return response()->json(['success' => false, 'message' => 'No checkout URL.', 'errors' => (object) []], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => ['checkout_url' => $checkoutUrl],
        ]);
    }

    public function confirmCheckout(Request $request, Ad $ad, FulfillAdvertiserStripeCheckout $fulfill): JsonResponse
    {
        $this->authorizeAd($request, $ad);

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
                'message' => 'Stripe not configured.',
                'errors' => (object) [],
            ], 422);
        }

        try {
            Stripe::setApiKey((string) config('services.stripe.secret'));
            $session = StripeCheckoutSession::retrieve($sessionId);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? $e->getMessage() : 'Could not verify payment.',
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

        $metadataAdId = (int) ($session->metadata['ad_id'] ?? 0);
        if ($metadataAdId !== (int) $ad->id) {
            return response()->json([
                'success' => false,
                'message' => 'Payment session does not match this ad.',
                'errors' => (object) [],
            ], 422);
        }

        $fulfilled = $fulfill($session);
        $ad->refresh();

        $message = $fulfilled
            ? ($ad->status === 'pending_approval'
                ? 'Payment successful. Your ad is pending administrator approval.'
                : 'Payment successful. Your ad is now published.')
            : 'Payment is still processing. Pull to refresh in a moment.';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'fulfilled' => $fulfilled,
                'ad' => $this->toAdPayload($ad),
            ],
        ]);
    }

    private function validateAd(Request $request, bool $isCreate = true): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:140'],
            'description' => ['required', 'string', 'max:5000'],
            'cta_url' => ['required', 'url:http,https', 'max:2048'],
            'image_url' => [$isCreate ? 'nullable' : 'sometimes', 'nullable', 'string', 'max:2048'],
            'image' => [$isCreate ? 'nullable' : 'sometimes', 'nullable', 'image', 'max:5120'],
            'clear_image' => ['sometimes', 'boolean'],
        ];
        $validated = $request->validate($rules);

        $imageUrl = trim((string) ($validated['image_url'] ?? ''));
        if ($imageUrl !== '' && ! preg_match('#^(https?://|/storage/)#i', $imageUrl)) {
            throw ValidationException::withMessages([
                'image_url' => 'Image URL must start with http://, https://, or /storage/.',
            ]);
        }

        return $validated;
    }

    private function resolveImageUrl(Request $request, array $validated, ?string $fallback = null): ?string
    {
        if ($request->boolean('clear_image')) {
            return null;
        }

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('ads', 'public');
            return '/storage/'.$path;
        }

        if (array_key_exists('image_url', $validated)) {
            $explicit = trim((string) ($validated['image_url'] ?? ''));
            return $explicit !== '' ? $explicit : null;
        }

        return $fallback;
    }

    private function toAdPayload(Ad $ad): array
    {
        $viewCount = $ad->analyticsEvents()->where('event_type', 'view')->count();
        $clickCount = $ad->analyticsEvents()->where('event_type', 'click')->count();

        return [
            'uuid' => $ad->uuid,
            'title' => $ad->title,
            'description' => $ad->description,
            'cta_url' => $ad->cta_url,
            'image_url' => $ad->image_url,
            'status' => $ad->status,
            'price_cents' => $ad->price_cents,
            'currency' => $ad->currency,
            'paid_at' => optional($ad->paid_at)?->toIso8601String(),
            'published_at' => optional($ad->published_at)?->toIso8601String(),
            'created_at' => optional($ad->created_at)?->toIso8601String(),
            'updated_at' => optional($ad->updated_at)?->toIso8601String(),
            'analytics' => [
                'views' => $viewCount,
                'clicks' => $clickCount,
                'ctr' => $viewCount > 0 ? round(($clickCount / $viewCount) * 100, 2) : 0.0,
            ],
            'meta' => $ad->meta ?? [],
            'is_publicly_visible' => $ad->isPubliclyVisible(),
        ];
    }

    private function authorizeAd(Request $request, Ad $ad): void
    {
        abort_unless($ad->user_id === $request->user()->id, 404);
    }

    private function defaultCurrency(): string
    {
        return strtolower((string) config('ads.currency', 'USD'));
    }

    private function adsRoutePrefix(Request $request): string
    {
        $user = $request->user();
        if ($user->hasRole('provider')) {
            return 'provider.ads';
        }
        if ($user->hasRole('advertiser') && ! $user->hasRole('user')) {
            return 'advertiser.ads';
        }

        return 'user.ads';
    }
}
