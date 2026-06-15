<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Actions\Advertiser\FulfillAdApplePurchase;
use App\Actions\AiAssistant\FulfillAiAssistantAppleSubscription;
use App\Actions\Library\FulfillLibraryApplePurchase;
use App\Actions\Provider\FulfillProviderAppleSubscription;
use App\Actions\Video\FulfillVideoApplePurchase;
use App\Models\Ad;
use App\Http\Controllers\Api\Mobile\Concerns\DetectsMobileClient;
use App\Http\Controllers\Controller;
use App\Models\AiAssistantSubscription;
use App\Models\LibraryItem;
use App\Models\ProviderSubscription;
use App\Models\ServiceProvider;
use App\Models\SubscriptionPlan;
use App\Models\VideoEmbed;
use App\Support\AdApplePurchaseLogger;
use App\Support\AppleIapConfig;
use App\Support\AppleIapPurchaseLogger;
use App\Support\LibraryEbookPurchaseLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class MobileAppleIapController extends Controller
{
    use DetectsMobileClient;

    private const IAP_UNAVAILABLE_MESSAGE = 'Purchases are temporarily unavailable. Please try again later or contact support.';

    public function config(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'apple_iap_configured' => AppleIapConfig::configured(),
                'bundle_id' => AppleIapConfig::bundleId(),
                'sandbox' => AppleIapConfig::useSandbox(),
                'ai_assistant_product_id' => AppleIapConfig::aiAssistantProductId(),
                'library_ebook_product_id' => AppleIapConfig::libraryEbookProductId(),
                'library_ebook_credit_product_id' => AppleIapConfig::libraryEbookCreditProductId(),
                'library_ebook_standard_price_cents' => \App\Support\LibraryEbookPricing::standardPriceCents(),
                'library_product_prefix' => trim((string) config('services.apple_iap.library_product_prefix', '')),
                'provider_product_prefix' => AppleIapConfig::providerProductPrefix(),
                'provider_monthly_product_id' => AppleIapConfig::providerMonthlyProductId(),
                'provider_yearly_product_id' => AppleIapConfig::providerYearlyProductId(),
                'video_product_prefix' => AppleIapConfig::videoProductPrefix(),
                'ad_publish_product_id' => AppleIapConfig::adPublishProductId(),
                'ios_requires_apple_iap' => true,
            ],
        ]);
    }

    public function purchaseLibraryItem(
        Request $request,
        LibraryItem $item,
        FulfillLibraryApplePurchase $fulfill,
    ): JsonResponse {
        abort_unless($item->is_active, 404);

        $validated = $request->validate([
            'transaction_id' => ['required', 'string', 'max:128'],
        ]);

        $userId = (int) $request->user()->id;
        LibraryEbookPurchaseLogger::logControllerRequest($request, $item, $userId);

        if (! $this->mobileClientIsIos($request)) {
            LibraryEbookPurchaseLogger::logControllerRejected($userId, $item, 'not_ios_client');

            return response()->json([
                'success' => false,
                'message' => 'Apple purchases are only accepted from the iOS app.',
                'errors' => (object) [],
            ], 422);
        }

        if ($response = $this->ensureAppleIapConfigured($request, 'library.apple-purchase')) {
            LibraryEbookPurchaseLogger::logControllerRejected(
                $userId,
                $item,
                'apple_iap_not_configured',
                'Purchases are temporarily unavailable. Please try again later or contact support.',
            );

            return $response;
        }

        $transactionId = (string) $validated['transaction_id'];

        try {
            $fulfilled = $fulfill($item, $userId, $transactionId);
        } catch (RuntimeException $e) {
            LibraryEbookPurchaseLogger::logControllerFailure($userId, $item, $transactionId, $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? $e->getMessage() : 'Could not verify Apple purchase.',
                'errors' => (object) [],
            ], 422);
        }

        LibraryEbookPurchaseLogger::logControllerSuccess($userId, $item, $fulfilled);

        return response()->json([
            'success' => true,
            'message' => $fulfilled ? 'Purchase confirmed.' : 'Purchase is still processing.',
            'data' => [
                'fulfilled' => $fulfilled,
                'apple_product_id' => $item->appleProductId(),
            ],
        ]);
    }

    public function purchaseAiAssistant(
        Request $request,
        FulfillAiAssistantAppleSubscription $fulfill,
    ): JsonResponse {
        if (! Schema::hasTable('ai_assistant_subscriptions')) {
            return response()->json([
                'success' => false,
                'message' => 'AI add-on is not ready yet. Please run database migrations.',
                'errors' => (object) [],
            ], 422);
        }

        $validated = $request->validate([
            'transaction_id' => ['required', 'string', 'max:128'],
        ]);

        if (! $this->mobileClientIsIos($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Apple purchases are only accepted from the iOS app.',
                'errors' => (object) [],
            ], 422);
        }

        if ($response = $this->ensureAppleIapConfigured($request, 'ai-assistant.apple-purchase')) {
            return $response;
        }

        try {
            $subscription = $fulfill((int) $request->user()->id, (string) $validated['transaction_id']);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? $e->getMessage() : 'Could not verify Apple subscription.',
                'errors' => (object) [],
            ], 422);
        }

        if (! $subscription->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription is not active yet. Try Restore Purchases or contact support.',
                'errors' => (object) [],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'AI Assistant subscription is active.',
            'data' => [
                'subscription' => $subscription,
                'is_addon_active' => true,
            ],
        ]);
    }

    public function purchaseProviderPlan(
        Request $request,
        SubscriptionPlan $plan,
        FulfillProviderAppleSubscription $fulfill,
    ): JsonResponse {
        if (! in_array((string) $plan->status, ['active'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'This plan is not available.',
                'errors' => (object) [],
            ], 422);
        }

        $validated = $request->validate([
            'transaction_id' => ['required', 'string', 'max:128'],
        ]);

        if (! $this->mobileClientIsIos($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Apple purchases are only accepted from the iOS app.',
                'errors' => (object) [],
            ], 422);
        }

        if ($response = $this->ensureAppleIapConfigured($request, 'provider.apple-purchase')) {
            return $response;
        }

        if ((int) $plan->price_cents <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'This plan is free and does not require purchase.',
                'errors' => (object) [],
            ], 422);
        }

        $provider = $this->resolveProvider($request);
        $types = is_array($provider->service_types) ? $provider->service_types : [];
        if (! SubscriptionPlan::query()->whereKey($plan->id)->forProviderServiceTypeValues($types)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This plan is not available for your service types.',
                'errors' => (object) [],
            ], 422);
        }

        try {
            $subscription = $fulfill(
                $provider,
                $plan,
                (int) $request->user()->id,
                (string) $validated['transaction_id'],
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? $e->getMessage() : 'Could not verify Apple subscription.',
                'errors' => (object) [],
            ], 422);
        }

        if (! in_array((string) $subscription->status, ['active', 'trialing', 'past_due'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription is not active yet. Try Restore Purchases or contact support.',
                'errors' => (object) [],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Provider subscription is active.',
            'data' => [
                'subscription' => $subscription,
                'apple_product_id' => $plan->appleProductId(),
            ],
        ]);
    }

    public function purchaseVideo(
        Request $request,
        VideoEmbed $video,
        FulfillVideoApplePurchase $fulfill,
    ): JsonResponse {
        abort_unless($video->is_active, 404);

        $validated = $request->validate([
            'transaction_id' => ['required', 'string', 'max:128'],
        ]);

        if (! $this->mobileClientIsIos($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Apple purchases are only accepted from the iOS app.',
                'errors' => (object) [],
            ], 422);
        }

        if ($response = $this->ensureAppleIapConfigured($request, 'video.apple-purchase')) {
            return $response;
        }

        $price = (float) ($video->price ?? 0);
        if ($price <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'This video is free and does not require purchase.',
                'errors' => (object) [],
            ], 422);
        }

        $userId = (int) $request->user()->id;

        try {
            $fulfilled = $fulfill($video, $userId, (string) $validated['transaction_id']);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? $e->getMessage() : 'Could not verify Apple purchase.',
                'errors' => (object) [],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $fulfilled ? 'Purchase confirmed.' : 'Purchase is still processing.',
            'data' => [
                'fulfilled' => $fulfilled,
                'apple_product_id' => $video->appleProductId(),
            ],
        ]);
    }

    public function purchaseAd(
        Request $request,
        Ad $ad,
        FulfillAdApplePurchase $fulfill,
    ): JsonResponse {
        abort_unless($ad->user_id === $request->user()->id, 404);

        $validated = $request->validate([
            'transaction_id' => ['required', 'string', 'max:128'],
        ]);

        $userId = (int) $request->user()->id;
        AdApplePurchaseLogger::logControllerRequest($request, $ad, $userId);

        if (! $this->mobileClientIsIos($request)) {
            AdApplePurchaseLogger::logControllerRejected($userId, $ad, 'not_ios_client');

            return response()->json([
                'success' => false,
                'message' => 'Apple purchases are only accepted from the iOS app.',
                'errors' => (object) [],
            ], 422);
        }

        if ($response = $this->ensureAppleIapConfigured($request, 'ad.apple-purchase')) {
            AdApplePurchaseLogger::logControllerRejected(
                $userId,
                $ad,
                'apple_iap_not_configured',
                'Purchases are temporarily unavailable. Please try again later or contact support.',
            );

            return $response;
        }

        if ($ad->status === 'pending_payment' && (int) $ad->price_cents > 0) {
            $standardPriceCents = AppleIapConfig::adPublishPriceCents();
            if ((int) $ad->price_cents !== $standardPriceCents) {
                $previousPriceCents = (int) $ad->price_cents;
                $ad->update([
                    'price_cents' => $standardPriceCents,
                    'currency' => strtoupper((string) config('ads.currency', 'USD')),
                ]);
                $ad->refresh();
                AdApplePurchaseLogger::logPriceNormalized($userId, $ad, $previousPriceCents);
            }
        }

        $productId = $ad->applePublishProductId();
        if ($productId === null) {
            AdApplePurchaseLogger::logControllerRejected(
                $userId,
                $ad,
                'iap_product_unavailable',
                'This ad is not available for In-App Purchase.',
            );

            return response()->json([
                'success' => false,
                'message' => config('app.debug')
                    ? 'This ad is not available for In-App Purchase (check price_cents and APPLE_AD_PUBLISH_PRODUCT_ID).'
                    : 'This ad is not available for In-App Purchase.',
                'errors' => (object) [],
            ], 422);
        }

        $transactionId = (string) $validated['transaction_id'];

        try {
            $fulfilled = $fulfill($ad, $userId, $transactionId);
        } catch (RuntimeException $e) {
            AdApplePurchaseLogger::logControllerFailure($userId, $ad, $transactionId, $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? $e->getMessage() : 'Could not verify Apple purchase.',
                'errors' => (object) [],
            ], 422);
        }

        AdApplePurchaseLogger::logControllerSuccess($userId, $ad->fresh(), $fulfilled);

        return response()->json([
            'success' => true,
            'message' => $fulfilled ? 'Ad payment confirmed.' : 'Payment is still processing.',
            'data' => [
                'fulfilled' => $fulfilled,
                'apple_product_id' => $productId,
                'ad' => $ad->fresh(),
            ],
        ]);
    }

    public function restore(Request $request, FulfillLibraryApplePurchase $fulfillLibrary): JsonResponse
    {
        if (! $this->mobileClientIsIos($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Restore is only available from the iOS app.',
                'errors' => (object) [],
            ], 422);
        }

        if ($response = $this->ensureAppleIapConfigured($request, 'iap.restore')) {
            return $response;
        }

        $validated = $request->validate([
            'library' => ['nullable', 'array'],
            'library.*.transaction_id' => ['required_with:library', 'string', 'max:128'],
            'library.*.product_id' => ['required_with:library', 'string', 'max:191'],
            'ai_assistant' => ['nullable', 'array'],
            'ai_assistant.transaction_id' => ['required_with:ai_assistant', 'string', 'max:128'],
            'provider_subscriptions' => ['nullable', 'array'],
            'provider_subscriptions.*.transaction_id' => ['required_with:provider_subscriptions', 'string', 'max:128'],
            'provider_subscriptions.*.product_id' => ['required_with:provider_subscriptions', 'string', 'max:191'],
            'videos' => ['nullable', 'array'],
            'videos.*.transaction_id' => ['required_with:videos', 'string', 'max:128'],
            'videos.*.product_id' => ['required_with:videos', 'string', 'max:191'],
        ]);

        $userId = (int) $request->user()->id;
        $restoredLibrary = 0;
        $restoredVideos = 0;
        $providerRestored = false;
        $errors = [];

        $ebookCreditProductId = AppleIapConfig::libraryEbookCreditProductId();

        foreach ($validated['library'] ?? [] as $entry) {
            $productId = (string) ($entry['product_id'] ?? '');
            $transactionId = (string) ($entry['transaction_id'] ?? '');

            if ($ebookCreditProductId !== '' && $productId === $ebookCreditProductId) {
                continue;
            }

            $item = LibraryItem::query()
                ->where('apple_product_id', $productId)
                ->first();

            if (! $item) {
                $item = LibraryItem::query()
                    ->get()
                    ->first(fn (LibraryItem $candidate) => $candidate->appleProductId() === $productId);
            }

            if (! $item) {
                $errors[] = "Unknown library product: {$productId}";

                continue;
            }

            try {
                if ($fulfillLibrary($item, $userId, $transactionId)) {
                    $restoredLibrary++;
                }
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }

        $aiRestored = false;
        if (! empty($validated['ai_assistant']['transaction_id'] ?? null) && Schema::hasTable('ai_assistant_subscriptions')) {
            try {
                $subscription = app(FulfillAiAssistantAppleSubscription::class)(
                    $userId,
                    (string) $validated['ai_assistant']['transaction_id']
                );
                $aiRestored = $subscription->isActive();
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }

        $provider = $request->user()?->serviceProvider;
        if ($provider) {
            foreach ($validated['provider_subscriptions'] ?? [] as $entry) {
                $productId = (string) ($entry['product_id'] ?? '');
                $transactionId = (string) ($entry['transaction_id'] ?? '');

                $plan = SubscriptionPlan::query()
                    ->where('apple_product_id', $productId)
                    ->first();

                if (! $plan) {
                    $plan = SubscriptionPlan::query()
                        ->get()
                        ->first(fn (SubscriptionPlan $candidate) => $candidate->appleProductId() === $productId);
                }

                if (! $plan) {
                    $errors[] = "Unknown provider plan product: {$productId}";

                    continue;
                }

                try {
                    $subscription = app(FulfillProviderAppleSubscription::class)(
                        $provider,
                        $plan,
                        $userId,
                        $transactionId,
                    );
                    if (in_array((string) $subscription->status, ['active', 'trialing', 'past_due'], true)) {
                        $providerRestored = true;
                    }
                } catch (RuntimeException $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }

        foreach ($validated['videos'] ?? [] as $entry) {
            $productId = (string) ($entry['product_id'] ?? '');
            $transactionId = (string) ($entry['transaction_id'] ?? '');

            $video = VideoEmbed::query()
                ->where('apple_product_id', $productId)
                ->first();

            if (! $video) {
                $video = VideoEmbed::query()
                    ->get()
                    ->first(fn (VideoEmbed $candidate) => $candidate->appleProductId() === $productId);
            }

            if (! $video) {
                $errors[] = "Unknown video product: {$productId}";

                continue;
            }

            try {
                if (app(FulfillVideoApplePurchase::class)($video, $userId, $transactionId)) {
                    $restoredVideos++;
                }
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }

        $providerActive = $providerRestored;
        if ($provider) {
            $providerActive = $providerActive || ProviderSubscription::query()
                ->where('service_provider_id', $provider->id)
                ->whereIn('status', ['trialing', 'active', 'past_due'])
                ->whereNotNull('apple_original_transaction_id')
                ->exists();
        }

        return response()->json([
            'success' => true,
            'message' => 'Restore completed.',
            'data' => [
                'library_restored' => $restoredLibrary,
                'ai_assistant_active' => $aiRestored || (AiAssistantSubscription::forUser($userId)?->isActive() ?? false),
                'provider_subscription_active' => $providerActive,
                'videos_restored' => $restoredVideos,
                'errors' => $errors,
            ],
        ]);
    }

    private function resolveProvider(Request $request): ServiceProvider
    {
        $provider = $request->user()?->serviceProvider;
        if (! $provider) {
            abort(response()->json([
                'success' => false,
                'message' => 'Provider profile not found.',
                'errors' => (object) [],
            ], 403));
        }

        return $provider;
    }

    private function ensureAppleIapConfigured(Request $request, string $endpoint): ?JsonResponse
    {
        if (AppleIapConfig::configured()) {
            return null;
        }

        AppleIapPurchaseLogger::logNotConfigured($endpoint, (int) $request->user()->id);

        return response()->json([
            'success' => false,
            'message' => self::IAP_UNAVAILABLE_MESSAGE,
            'errors' => (object) [],
        ], 422);
    }
}
