<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Actions\Library\FulfillLibraryStripeCheckout;
use App\Actions\Library\GrantLibraryItemAccess;
use App\Actions\Library\QueueLibraryEbookSummary;
use App\Actions\Library\RedeemEbookCoupon;
use App\Http\Controllers\Api\Mobile\Concerns\DetectsMobileClient;
use App\Http\Controllers\Controller;
use App\Models\EbookCoupon;
use App\Models\LibraryAuthor;
use App\Models\LibraryCategory;
use App\Models\LibraryItem;
use App\Models\LibraryUserAccess;
use App\Models\User;
use App\Support\AppleIapConfig;
use App\Support\LibraryEbookPricing;
use App\Support\StripeConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Stripe;

class MobileLibraryController extends Controller
{
    use DetectsMobileClient;

    public function browse(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', Rule::in(LibraryItem::supportedTypes())],
            'category' => ['nullable', 'string', 'max:120'],
            'author' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', 'string', 'max:32'],
            'favorites' => ['nullable', 'boolean'],
        ]);

        $query = LibraryItem::query()
            ->with(['category', 'libraryAuthor'])
            ->active()
            ->availableInRegion(LibraryItem::regionForCountry($user->country ?? null));

        if (! empty($validated['search'])) {
            $query->search($validated['search']);
        }

        if (! empty($validated['category'])) {
            $category = LibraryCategory::where('slug', $validated['category'])->first();
            if ($category) {
                $query->inCategory($category->id);
            }
        }

        if (! empty($validated['author'])) {
            $author = LibraryAuthor::where('slug', $validated['author'])->first();
            if ($author) {
                $query->where('author_id', $author->id);
            }
        }

        if (! empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (! empty($validated['favorites'])) {
            $query->whereHas('userAccess', function ($q) use ($user) {
                $q->where('user_id', $user->id)->where('is_favorite', true);
            });
        }

        match ($validated['sort'] ?? 'newest') {
            'newest' => $query->orderByDesc('created_at'),
            'popular' => $query->orderByDesc('view_count')->orderByDesc('created_at'),
            'title' => $query->orderBy('title'),
            default => $query->orderByDesc('is_featured')->orderByDesc('created_at'),
        };

        $paginator = $query->paginate((int) ($validated['per_page'] ?? 16));

        $items = $paginator->getCollection()->map(function (LibraryItem $item) use ($user) {
            $access = $item->userAccess()->where('user_id', $user->id)->first();

            return [
                'id' => $item->id,
                'uuid' => $item->uuid,
                'title' => $item->title,
                'slug' => $item->slug,
                'type' => $item->type,
                'description' => $item->description ? mb_strimwidth(strip_tags((string) $item->description), 0, 280, '…') : null,
                'cover_image_url' => $item->cover_image_url,
                'is_premium' => (bool) $item->is_premium,
                'price' => $item->price !== null ? (float) $item->price : null,
                'currency' => $item->currency,
                'category' => $item->category ? ['name' => $item->category->name, 'slug' => $item->category->slug] : null,
                'author' => $item->author,
                'is_favorite' => (bool) ($access?->is_favorite),
                'has_access' => (bool) ($access?->purchased_at),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'items' => [
                    'data' => $items,
                    'meta' => [
                        'current_page' => $paginator->currentPage(),
                        'last_page' => $paginator->lastPage(),
                        'per_page' => $paginator->perPage(),
                        'total' => $paginator->total(),
                    ],
                ],
                'categories' => LibraryCategory::active()->ordered()->get(['id', 'name', 'slug']),
                'types' => LibraryItem::typeOptionsWithCounts(),
            ],
        ]);
    }

    public function show(Request $request, LibraryItem $item): JsonResponse
    {
        abort_unless($item->is_active, 404);
        $this->abortIfNotAvailableInUserRegion($request->user(), $item);

        $item->load(['category', 'libraryAuthor']);
        $item->incrementViews();
        $item->recordAccess($request->user());

        $userAccess = $item->userAccess()->where('user_id', $request->user()->id)->first();
        $hasAccess = (bool) $userAccess?->purchased_at;
        $requiresPaidAccess = $this->requiresPaidAccess($item);
        $signupCoupon = EbookCoupon::activeSignupCouponForUser((int) $request->user()->id);

        $related = LibraryItem::query()
            ->active()
            ->availableInRegion(LibraryItem::regionForCountry($request->user()->country ?? null))
            ->where('id', '!=', $item->id)
            ->where('category_id', $item->category_id)
            ->with('libraryAuthor')
            ->limit(6)
            ->get()
            ->map(fn (LibraryItem $r) => [
                'title' => $r->title,
                'slug' => $r->slug,
                'type' => $r->type,
                'cover_image_url' => $r->cover_image_url,
                'price' => $r->price !== null ? (float) $r->price : null,
                'currency' => $r->currency,
            ]);

        if ($item->type === 'ebook') {
            app(QueueLibraryEbookSummary::class)($item);
            $item = $item->fresh() ?? $item;
        }

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'item' => [
                    'id' => $item->id,
                    'uuid' => $item->uuid,
                    'title' => $item->title,
                    'slug' => $item->slug,
                    'type' => $item->type,
                    'description' => $item->description,
                    'cover_image_url' => $item->cover_image_url,
                    'is_premium' => (bool) $item->is_premium,
                    'price' => $item->price !== null ? (float) $item->price : null,
                    'currency' => $item->currency,
                    'category' => $item->category ? ['name' => $item->category->name, 'slug' => $item->category->slug] : null,
                    'author' => $item->author,
                    'publisher' => $item->publisher,
                    'published_at' => $item->published_at?->toDateString(),
                    'publication_year' => $item->publication_year,
                    'isbn' => $item->isbn,
                    'page_count' => $item->page_count,
                    'language' => $item->language,
                    'duration_seconds' => $item->duration_seconds,
                    'estimated_reading_minutes' => $item->estimated_reading_minutes,
                    'has_audio_companion' => (bool) $item->has_audio_companion,
                    'file_size' => $item->file_size,
                    'view_count' => $item->view_count,
                    'ai_summary' => $item->type === 'ebook' ? $item->ai_summary : null,
                ],
                'user_access' => $userAccess,
                'has_access' => $hasAccess,
                'requires_paid_access' => $requiresPaidAccess,
                'stripe_configured' => StripeConfig::checkoutConfigured(),
                'apple_product_id' => $requiresPaidAccess ? $item->appleProductId() : null,
                'uses_ebook_credit_iap' => $requiresPaidAccess && $item->usesEbookCreditIap(),
                'ebook_standard_price_cents' => LibraryEbookPricing::standardPriceCents(),
                'ebook_standard_currency' => LibraryEbookPricing::currency(),
                'ebook_coupon_available' => $signupCoupon !== null && ! $hasAccess && $requiresPaidAccess,
                'apple_iap_configured' => AppleIapConfig::configured(),
                'ios_requires_apple_iap' => true,
                'manual_payment_pending' => (bool) ($userAccess?->manual_payment_requested_at && ! $userAccess?->purchased_at),
                'related' => $related,
            ],
        ]);
    }

    public function my(Request $request): JsonResponse
    {
        $user = $request->user();
        $section = $request->query('section', 'purchased');
        $perPage = min(24, max(1, (int) $request->query('per_page', 12)));

        $userRegion = LibraryItem::regionForCountry($user->country ?? null);

        if ($section === 'available') {
            $paginator = LibraryItem::query()
                ->active()
                ->availableInRegion($userRegion)
                ->where(function ($q) use ($user) {
                    $q->whereDoesntHave('userAccess', function ($access) use ($user) {
                        $access->where('user_id', $user->id)->whereNotNull('purchased_at');
                    });
                })
                ->with([
                    'libraryAuthor',
                    'category',
                    'userAccess' => function ($q) use ($user) {
                        $q->where('user_id', $user->id);
                    },
                ])
                ->orderByDesc('is_featured')
                ->orderByDesc('created_at')
                ->paginate($perPage, ['*'], 'page');
        } else {
            $paginator = LibraryItem::query()
                ->active()
                ->whereHas('userAccess', function ($q) use ($user) {
                    $q->where('user_id', $user->id)->whereNotNull('purchased_at');
                })
                ->with([
                    'libraryAuthor',
                    'category',
                    'userAccess' => function ($q) use ($user) {
                        $q->where('user_id', $user->id);
                    },
                ])
                ->orderByDesc(
                    LibraryUserAccess::query()
                        ->select('purchased_at')
                        ->whereColumn('library_item_id', 'library_items.id')
                        ->where('user_id', $user->id)
                        ->limit(1)
                )
                ->paginate($perPage, ['*'], 'page');
        }

        $paginator->setCollection(
            $paginator->getCollection()->map(function (LibraryItem $item) use ($user) {
                $access = $item->relationLoaded('userAccess')
                    ? $item->userAccess->first()
                    : $item->userAccess()->where('user_id', $user->id)->first();

                return [
                    'id' => $item->id,
                    'uuid' => $item->uuid,
                    'title' => $item->title,
                    'slug' => $item->slug,
                    'type' => $item->type,
                    'description' => $item->description ? mb_strimwidth(strip_tags((string) $item->description), 0, 280, '…') : null,
                    'cover_image_url' => $item->cover_image_url,
                    'is_premium' => (bool) $item->is_premium,
                    'price' => $item->price !== null ? (float) $item->price : null,
                    'currency' => $item->currency,
                    'category' => $item->category ? ['name' => $item->category->name, 'slug' => $item->category->slug] : null,
                    'author' => $item->author,
                    'is_favorite' => (bool) ($access?->is_favorite),
                    'has_access' => (bool) ($access?->purchased_at),
                ];
            })
        );

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'section' => $section,
                'items' => $paginator,
            ],
        ]);
    }

    public function toggleFavorite(Request $request, LibraryItem $item): JsonResponse
    {
        abort_unless($item->is_active, 404);
        $this->abortIfNotAvailableInUserRegion($request->user(), $item);

        $access = $item->userAccess()->firstOrCreate([
            'user_id' => $request->user()->id,
        ]);
        $access->toggleFavorite();

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'is_favorite' => (bool) $access->fresh()->is_favorite,
            ],
        ]);
    }

    public function stripeCheckout(Request $request, LibraryItem $item): JsonResponse
    {
        abort_unless($item->is_active, 404);
        $this->abortIfNotAvailableInUserRegion($request->user(), $item);

        if ($this->mobileClientIsIos($request)) {
            return response()->json([
                'success' => false,
                'message' => 'On iOS, use In-App Purchase to buy this title.',
                'errors' => (object) [],
            ], 422);
        }

        $requiresPaidAccess = $this->requiresPaidAccess($item);
        if (! $item->is_premium && ! $requiresPaidAccess) {
            return response()->json([
                'success' => false,
                'message' => 'This item is already available for free.',
                'errors' => (object) [],
            ], 422);
        }

        if ($item->price === null || (float) $item->price <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'This item is not available for online purchase.',
                'errors' => (object) [],
            ], 422);
        }

        if ($item->userAccess()
            ->where('user_id', $request->user()->id)
            ->whereNotNull('purchased_at')
            ->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You already have access to this title.',
                'errors' => (object) [],
            ], 422);
        }

        if (! StripeConfig::checkoutConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Online checkout is not available right now.',
                'errors' => (object) [],
            ], 422);
        }

        Stripe::setApiKey((string) config('services.stripe.secret'));
        $currency = strtolower((string) ($item->currency ?? 'USD'));
        $unitAmount = (int) round((float) $item->price * 100);

        if ($currency === 'usd' && $unitAmount < 50) {
            return response()->json([
                'success' => false,
                'message' => 'This price is below the minimum for card payments.',
                'errors' => (object) [],
            ], 422);
        }

        $description = Str::limit(strip_tags((string) $item->description), 450);
        if ($description === '') {
            $description = 'Digital library item';
        }

        try {
            $session = StripeCheckoutSession::create([
                'mode' => 'payment',
                'customer_email' => $request->user()->email,
                'client_reference_id' => (string) $request->user()->id,
                'success_url' => url('/?checkout=success&session_id={CHECKOUT_SESSION_ID}'),
                'cancel_url' => url('/?checkout=cancelled'),
                'metadata' => [
                    'app' => 'library',
                    'library_item_id' => (string) $item->id,
                    'user_id' => (string) $request->user()->id,
                ],
                'line_items' => [[
                    'price_data' => [
                        'currency' => $currency,
                        'unit_amount' => $unitAmount,
                        'product_data' => [
                            'name' => $item->title,
                            'description' => $description,
                        ],
                    ],
                    'quantity' => 1,
                ]],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? 'Payment could not start: '.$e->getMessage() : 'Payment could not start.',
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

    public function confirmCheckout(Request $request, LibraryItem $item): JsonResponse
    {
        $sessionId = $request->input('session_id');
        if (! is_string($sessionId) || trim($sessionId) === '') {
            return response()->json([
                'success' => false,
                'message' => 'Missing session ID.',
                'errors' => (object) [],
            ], 422);
        }

        $secret = config('services.stripe.secret');
        if (! is_string($secret) || $secret === '') {
            return response()->json([
                'success' => false,
                'message' => 'Payments are not configured.',
                'errors' => (object) [],
            ], 422);
        }

        try {
            Stripe::setApiKey($secret);
            $session = StripeCheckoutSession::retrieve($sessionId);
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

        $fulfill = app(FulfillLibraryStripeCheckout::class);
        $fulfilled = $fulfill($session);

        return response()->json([
            'success' => true,
            'message' => $fulfilled ? 'Purchase confirmed.' : 'Payment is still processing.',
            'data' => [
                'fulfilled' => $fulfilled,
            ],
        ]);
    }

    public function grantFree(Request $request, LibraryItem $item): JsonResponse
    {
        abort_unless($item->is_active, 404);
        $this->abortIfNotAvailableInUserRegion($request->user(), $item);

        if ($this->requiresPaidAccess($item)) {
            return response()->json([
                'success' => false,
                'message' => 'Use checkout to unlock this item.',
                'errors' => (object) [],
            ], 422);
        }

        app(GrantLibraryItemAccess::class)($item, (int) $request->user()->id, [
            'purchase_amount' => 0,
            'purchase_currency' => $item->currency ?? 'USD',
            'purchase_source' => 'free',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Added to your library.',
            'data' => (object) [],
        ]);
    }

    public function redeemCoupon(Request $request, LibraryItem $item, RedeemEbookCoupon $redeem): JsonResponse
    {
        abort_unless($item->is_active, 404);
        $this->abortIfNotAvailableInUserRegion($request->user(), $item);

        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:32'],
        ]);

        $userId = (int) $request->user()->id;
        $code = isset($validated['code']) ? strtoupper(trim((string) $validated['code'])) : '';

        $coupon = $code !== ''
            ? EbookCoupon::query()->where('code', $code)->first()
            : EbookCoupon::activeSignupCouponForUser($userId);

        if (! $coupon) {
            return response()->json([
                'success' => false,
                'message' => $code !== ''
                    ? 'Coupon code not found.'
                    : 'You do not have an active free ebook coupon.',
                'errors' => (object) [],
            ], 422);
        }

        try {
            $access = $redeem($coupon, $item, $userId);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => (object) [],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Coupon redeemed. This title is now in your library.',
            'data' => [
                'has_access' => $access->purchased_at !== null,
            ],
        ]);
    }

    public function updateProgress(Request $request, LibraryItem $item): JsonResponse
    {
        abort_unless($item->is_active, 404);
        $this->abortIfNotAvailableInUserRegion($request->user(), $item);
        abort_unless($this->userHasAccess($request->user()->id, $item), 403);

        $validated = $request->validate([
            'mode' => ['required', 'string', 'in:reading,audio'],
            'progress' => ['required', 'array'],
            'progress.page' => ['nullable', 'integer', 'min:1'],
            'progress.total_pages' => ['nullable', 'integer', 'min:1'],
            'progress.position' => ['nullable', 'numeric', 'min:0'],
            'progress.duration' => ['nullable', 'numeric', 'min:0'],
            'progress.percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $access = $item->userAccess()->where('user_id', $request->user()->id)->firstOrFail();
        $access->updateProgress($validated['progress'], $validated['mode']);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'progress' => $access->fresh()->progress,
            ],
        ]);
    }

    public function summary(Request $request, LibraryItem $item): JsonResponse
    {
        abort_unless($item->is_active, 404);
        $this->abortIfNotAvailableInUserRegion($request->user(), $item);
        abort_unless($item->type === 'ebook', 404);

        $fresh = LibraryItem::query()->whereKey($item->id)->firstOrFail();
        if ($fresh->type === 'ebook') {
            app(QueueLibraryEbookSummary::class)($fresh);
            $fresh = $fresh->fresh() ?? $fresh;
        }

        $message = null;
        if (! $fresh->ai_summary) {
            $message = 'Summary is being generated. Please check back shortly.';
        }

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'summary' => $fresh->ai_summary,
                'message' => $message,
                'generated_at' => optional($fresh->ai_summary_generated_at)?->toIso8601String(),
            ],
        ]);
    }

    public function streamUrls(Request $request, LibraryItem $item): JsonResponse
    {
        abort_unless($item->is_active, 404);
        $this->abortIfNotAvailableInUserRegion($request->user(), $item);
        abort_unless($this->userHasAccess($request->user()->id, $item), 403);

        $item->recordAccess($request->user());

        $token = bin2hex(random_bytes(32));
        Cache::put('lib_mob_media_'.$token, [
            'user_id' => $request->user()->id,
            'item_id' => $item->id,
        ], now()->addMinutes(20));

        $forwardedProto = strtolower(trim(explode(',', (string) $request->header('X-Forwarded-Proto'))[0] ?? ''));
        $forwardedSsl = strtolower((string) $request->header('X-Forwarded-Ssl'));
        $configuredScheme = strtolower((string) parse_url((string) config('app.url'), PHP_URL_SCHEME));
        $scheme = $request->getScheme();
        if ($forwardedProto === 'https' || $forwardedSsl === 'on' || $configuredScheme === 'https') {
            $scheme = 'https';
        }

        $base = $scheme.'://'.$request->getHttpHost().'/api/mobile/library/stream/'.$token;

        $urls = [];
        if ($item->type === 'ebook') {
            $urls['pdf'] = $base.'?asset=pdf';
            if ($item->audio_file_path) {
                $urls['audio'] = $base.'?asset=audio';
            }
        } else {
            $urls['audio'] = $base.'?asset=audio';
        }

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'stream_urls' => $urls,
                'expires_in_seconds' => 1200,
            ],
        ]);
    }

    private function abortIfNotAvailableInUserRegion(User $user, LibraryItem $item): void
    {
        if ($this->userHasAccess($user->id, $item)) {
            return;
        }

        $region = LibraryItem::regionForCountry($user->country ?? null);
        if ($region === null) {
            return;
        }

        $regions = $item->regions ?? null;
        if (! is_array($regions) || count($regions) === 0) {
            return;
        }

        abort_unless(in_array($region, $regions, true), 404);
    }

    private function requiresPaidAccess(LibraryItem $item): bool
    {
        return $item->is_premium || (float) ($item->price ?? 0) > 0;
    }

    private function userHasAccess(int $userId, LibraryItem $item): bool
    {
        return $item->userAccess()
            ->where('user_id', $userId)
            ->whereNotNull('purchased_at')
            ->exists();
    }
}
