<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Actions\AiAssistant\FulfillAiAssistantAppleSubscription;
use App\Actions\Library\FulfillLibraryApplePurchase;
use App\Http\Controllers\Api\Mobile\Concerns\DetectsMobileClient;
use App\Http\Controllers\Controller;
use App\Models\AiAssistantSubscription;
use App\Models\LibraryItem;
use App\Support\AppleIapConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class MobileAppleIapController extends Controller
{
    use DetectsMobileClient;

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
                'library_product_prefix' => trim((string) config('services.apple_iap.library_product_prefix', '')),
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

        if (! $this->mobileClientIsIos($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Apple purchases are only accepted from the iOS app.',
                'errors' => (object) [],
            ], 422);
        }

        if (! AppleIapConfig::configured()) {
            return response()->json([
                'success' => false,
                'message' => 'Apple In-App Purchase is not configured on the server.',
                'errors' => (object) [],
            ], 422);
        }

        $userId = (int) $request->user()->id;

        try {
            $fulfilled = $fulfill($item, $userId, (string) $validated['transaction_id']);
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

        if (! AppleIapConfig::configured()) {
            return response()->json([
                'success' => false,
                'message' => 'Apple In-App Purchase is not configured on the server.',
                'errors' => (object) [],
            ], 422);
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

    public function restore(Request $request, FulfillLibraryApplePurchase $fulfillLibrary): JsonResponse
    {
        if (! $this->mobileClientIsIos($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Restore is only available from the iOS app.',
                'errors' => (object) [],
            ], 422);
        }

        if (! AppleIapConfig::configured()) {
            return response()->json([
                'success' => false,
                'message' => 'Apple In-App Purchase is not configured on the server.',
                'errors' => (object) [],
            ], 422);
        }

        $validated = $request->validate([
            'library' => ['nullable', 'array'],
            'library.*.transaction_id' => ['required_with:library', 'string', 'max:128'],
            'library.*.product_id' => ['required_with:library', 'string', 'max:191'],
            'ai_assistant' => ['nullable', 'array'],
            'ai_assistant.transaction_id' => ['required_with:ai_assistant', 'string', 'max:128'],
        ]);

        $userId = (int) $request->user()->id;
        $restoredLibrary = 0;
        $errors = [];

        foreach ($validated['library'] ?? [] as $entry) {
            $productId = (string) ($entry['product_id'] ?? '');
            $transactionId = (string) ($entry['transaction_id'] ?? '');

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

        return response()->json([
            'success' => true,
            'message' => 'Restore completed.',
            'data' => [
                'library_restored' => $restoredLibrary,
                'ai_assistant_active' => $aiRestored || (AiAssistantSubscription::forUser($userId)?->isActive() ?? false),
                'errors' => $errors,
            ],
        ]);
    }
}
