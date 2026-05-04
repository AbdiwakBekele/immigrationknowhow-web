<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\LibraryAuthor;
use App\Models\LibraryCategory;
use App\Models\LibraryItem;
use App\Models\LibraryUserAccess;
use App\Models\User;
use App\Support\StripeConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Stripe;

class MobileLibraryController extends Controller
{
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
            ->active();

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

        $related = LibraryItem::query()
            ->active()
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
                    'ai_summary' => $item->type === 'ebook' ? $item->ai_summary : null,
                    'ai_summary_status' => $item->type === 'ebook' ? $item->ai_summary_status : null,
                ],
                'user_access' => $userAccess,
                'has_access' => $hasAccess,
                'requires_paid_access' => $requiresPaidAccess,
                'stripe_configured' => StripeConfig::checkoutConfigured(),
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

        if ($section === 'available') {
            $paginator = LibraryItem::query()
                ->active()
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
                'success_url' => route('library.purchase.return', [], true).'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('library.purchase.cancel', $item, true),
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

        $access = $item->userAccess()->firstOrCreate([
            'user_id' => $request->user()->id,
        ]);

        if (! $access->purchased_at) {
            $access->update([
                'purchased_at' => now(),
                'purchase_amount' => 0,
                'purchase_currency' => $item->currency ?? 'USD',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Added to your library.',
            'data' => (object) [],
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
        abort_unless($this->userHasAccess($request->user()->id, $item), 403);

        $fresh = LibraryItem::query()->whereKey($item->id)->firstOrFail();
        $message = null;
        if (! $fresh->ai_summary) {
            $message = 'Summary not available yet. It is generated when admin uploads this ebook.';
        }

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'summary' => $fresh->ai_summary,
                'status' => $fresh->ai_summary_status,
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

        $base = rtrim((string) config('app.url'), '/').'/api/mobile/library/stream/'.$token;

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
