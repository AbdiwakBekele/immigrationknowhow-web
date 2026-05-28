<?php

namespace App\Http\Controllers;

use App\Actions\Library\FulfillLibraryStripeCheckout;
use App\Models\LibraryAuthor;
use App\Models\LibraryCategory;
use App\Models\LibraryItem;
use App\Models\LibraryUserAccess;
use App\Services\Library\LibraryMediaStreamService;
use App\Support\StripeConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Stripe;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LibraryController extends Controller
{
    private function regionForCurrentUser(): ?string
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        return LibraryItem::regionForCountry($user->country ?? null);
    }

    private function abortIfNotAvailableInUserRegion(LibraryItem $item): void
    {
        abort_unless($this->itemAvailableInUserRegion($item), 404);
    }

    private function itemAvailableInUserRegion(LibraryItem $item): bool
    {
        $region = $this->regionForCurrentUser();
        if ($region === null) {
            return true;
        }

        $regions = $item->regions ?? null;
        if (! is_array($regions) || count($regions) === 0) {
            return true;
        }

        return in_array($region, $regions, true);
    }

    private const LIBRARY_CART_SESSION_KEY = 'library_cart_ids';

    /**
     * @return array<int, int>
     */
    private function getLibraryCartIds(): array
    {
        $raw = session()->get(self::LIBRARY_CART_SESSION_KEY, []);
        if (! is_array($raw)) {
            return [];
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $raw))));

        return $ids;
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function setLibraryCartIds(array $ids): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        session()->put(self::LIBRARY_CART_SESSION_KEY, $ids);
    }

    /**
     * @return Collection<int, LibraryItem>
     */
    private function syncAndResolveCartItems(): Collection
    {
        $ids = $this->getLibraryCartIds();
        if ($ids === []) {
            return collect();
        }

        $loaded = LibraryItem::query()
            ->with(['category', 'libraryAuthor'])
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $keep = [];
        foreach ($ids as $id) {
            $item = $loaded->get($id);
            if (! $item) {
                continue;
            }
            if ($this->userHasAccess($item)) {
                continue;
            }
            if (! $this->itemAvailableInUserRegion($item)) {
                continue;
            }
            $requiresPaid = $this->requiresPaidAccess($item);
            if (! $requiresPaid) {
                continue;
            }
            if ($item->price === null || (float) $item->price <= 0) {
                continue;
            }
            $keep[] = (int) $item->id;
        }

        $this->setLibraryCartIds($keep);

        if ($keep === []) {
            return collect();
        }

        $currencyOrder = [];
        foreach ($keep as $kid) {
            $item = $loaded->get($kid);
            if ($item) {
                $currencyOrder[strtoupper((string) ($item->currency ?? 'USD'))] = true;
            }
        }
        if (count($currencyOrder) > 1) {
            $firstCurrency = strtoupper((string) ($loaded->get($keep[0])?->currency ?? 'USD'));
            $keep = array_values(array_filter($keep, function (int $id) use ($loaded, $firstCurrency): bool {
                $item = $loaded->get($id);

                return $item && strtoupper((string) ($item->currency ?? 'USD')) === $firstCurrency;
            }));
            $this->setLibraryCartIds($keep);
        }

        if ($keep === []) {
            return collect();
        }

        $models = LibraryItem::query()
            ->with(['category', 'libraryAuthor'])
            ->whereIn('id', $keep)
            ->get();

        return $models
            ->sortBy(static fn (LibraryItem $item): int => array_search($item->id, $keep, true) ?: 0)
            ->values();
    }

    public function cartPayload(): array
    {
        $items = $this->syncAndResolveCartItems();
        $currency = strtoupper((string) ($items->first()?->currency ?? 'USD'));
        $total = round($items->reduce(static fn (float $carry, LibraryItem $item): float => $carry + (float) ($item->price ?? 0), 0.0), 2);

        return [
            'items' => $items->values(),
            'total' => $total,
            'currency' => $currency,
            'stripeConfigured' => $this->stripeIsConfigured(),
            'manualPaymentsAvailable' => $this->manualPaymentsEnabled(),
        ];
    }

    /** Cart index route after add-to-cart, checkout errors/cancel — provider portal sends cart_portal=provider. */
    protected function resolvedCartRouteName(?Request $request = null): string
    {
        $request ??= request();

        return $request->input('cart_portal') === 'provider'
            ? 'provider.library.cart'
            : 'library.cart';
    }

    public function cart(): Response
    {
        return Inertia::render('Library/Cart', $this->cartPayload());
    }

    public function addToCart(Request $request, LibraryItem $item): RedirectResponse
    {
        abort_unless($item->is_active, 404);

        if (! $this->itemAvailableInUserRegion($item)) {
            return back()->with('error', 'This title is not available in your region.');
        }

        $requiresPaidAccess = $this->requiresPaidAccess($item);
        if (! $item->is_premium && ! $requiresPaidAccess) {
            return back()->with('info', 'This title is free — open it from the library without adding to cart.');
        }

        if ($item->price === null || (float) $item->price <= 0) {
            return back()->with('error', 'This title is not available for online purchase.');
        }

        if ($this->userHasAccess($item)) {
            return back()->with('info', 'You already have access to this title.');
        }

        $ids = $this->getLibraryCartIds();
        if (in_array($item->id, $ids, true)) {
            return redirect()
                ->route($this->resolvedCartRouteName($request))
                ->with('info', 'This title is already in your cart.');
        }

        $newCurrency = strtoupper((string) ($item->currency ?? 'USD'));
        if ($ids !== []) {
            $first = LibraryItem::query()->whereKey($ids[0])->first();
            $firstCurrency = strtoupper((string) ($first?->currency ?? 'USD'));
            if ($firstCurrency !== $newCurrency) {
                return back()->with('error', 'Your cart uses '.$firstCurrency.'. Remove items or complete checkout before adding titles in '.$newCurrency.'.');
            }
        }

        $ids[] = $item->id;
        $this->setLibraryCartIds($ids);

        return redirect()
            ->route($this->resolvedCartRouteName($request))
            ->with('success', 'Added to your cart.');
    }

    public function removeFromCart(LibraryItem $item): RedirectResponse
    {
        $ids = array_values(array_filter(
            $this->getLibraryCartIds(),
            static fn (int $id): bool => $id !== $item->id
        ));
        $this->setLibraryCartIds($ids);

        return back()->with('success', 'Removed from cart.');
    }

    public function checkoutCart(Request $request): RedirectResponse|Response|SymfonyResponse
    {
        $cartRouteName = $this->resolvedCartRouteName($request);
        $items = $this->syncAndResolveCartItems();

        if ($items->isEmpty()) {
            return redirect()
                ->route($cartRouteName)
                ->with('error', 'Your cart is empty or the titles are no longer available.');
        }

        if (! $this->stripeIsConfigured()) {
            if ($items->count() === 1 && $this->manualPaymentsEnabled()) {
                return redirect()->route('library.pay', $items->first());
            }

            return redirect()
                ->route($cartRouteName)
                ->with('error', config('app.debug')
                    ? 'Stripe checkout is not configured. Add STRIPE_KEY and STRIPE_SECRET, then run php artisan config:clear.'
                    : 'Online checkout is not available right now. Please try again later or contact support.');
        }

        $currency = strtolower((string) ($items->first()->currency ?? 'USD'));
        $lineItems = [];

        foreach ($items as $item) {
            $unitAmount = (int) round((float) $item->price * 100);
            if ($currency === 'usd' && $unitAmount < 50) {
                return redirect()
                    ->route($cartRouteName)
                    ->with('error', 'One or more prices are below the minimum for card payments. Please contact support.');
            }

            $description = Str::limit(strip_tags((string) $item->description), 450);
            if ($description === '') {
                $description = 'Digital library item';
            }

            $lineItems[] = [
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => $unitAmount,
                    'product_data' => [
                        'name' => $item->title,
                        'description' => $description,
                    ],
                ],
                'quantity' => 1,
            ];
        }

        $totalCents = (int) array_sum(array_map(
            static fn (array $row): int => (int) ($row['price_data']['unit_amount'] ?? 0),
            $lineItems
        ));
        if ($currency === 'usd' && $totalCents < 50) {
            return redirect()
                ->route($cartRouteName)
                ->with('error', 'The order total is below the minimum for card payments. Please contact support.');
        }

        $secret = config('services.stripe.secret');
        Stripe::setApiKey($secret);

        $itemIdsString = $items->pluck('id')->implode(',');

        $portal = $request->input('cart_portal') === 'provider' ? 'provider' : 'user';

        try {
            $session = StripeCheckoutSession::create([
                'mode' => 'payment',
                'customer_email' => auth()->user()->email,
                'client_reference_id' => (string) auth()->id(),
                'success_url' => route('library.purchase.return', [], true).'?session_id={CHECKOUT_SESSION_ID}&portal='.$portal,
                'cancel_url' => route($cartRouteName, [], true),
                'metadata' => [
                    'app' => 'library',
                    'library_item_ids' => $itemIdsString,
                    'user_id' => (string) auth()->id(),
                    'portal' => $portal,
                ],
                'line_items' => $lineItems,
                'custom_text' => [
                    'submit' => [
                        'message' => 'After payment, you will return to your library to read or listen.',
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Stripe library cart checkout session failed', [
                'library_item_ids' => $itemIdsString,
                'message' => $e->getMessage(),
            ]);

            return redirect()
                ->route($cartRouteName)
                ->with('error', config('app.debug')
                    ? 'Payment could not start: '.$e->getMessage()
                    : 'Payment could not start. Please try again or contact support.');
        }

        $checkoutUrl = $session->url;
        if (! is_string($checkoutUrl) || trim($checkoutUrl) === '') {
            return redirect()
                ->route($cartRouteName)
                ->with('error', 'Could not start checkout. Please try again.');
        }

        return Inertia::location($checkoutUrl);
    }

    public function index(Request $request): Response
    {
        $query = LibraryItem::query()
            ->with(['category', 'libraryAuthor'])
            ->active();

        $forcedType = $request->attributes->get('library_forced_type');
        $forcedType = is_string($forcedType) && in_array($forcedType, LibraryItem::supportedTypes(), true)
            ? $forcedType
            : null;

        // Apply filters
        if ($request->filled('search')) {
            $query->search($request->input('search'));
        }

        if ($request->filled('category')) {
            $category = LibraryCategory::where('slug', $request->input('category'))->first();
            if ($category) {
                $query->inCategory($category->id);
            }
        }

        if ($request->filled('author')) {
            $author = LibraryAuthor::where('slug', $request->input('author'))->first();
            if ($author) {
                $query->where('author_id', $author->id);
            }
        }

        if ($forcedType !== null) {
            $query->where('type', $forcedType);
        } elseif ($request->filled('type')) {
            $type = $request->input('type');
            if (in_array($type, LibraryItem::supportedTypes(), true)) {
                $query->where('type', $type);
            }
        }

        if ($request->filled('region')) {
            $region = $request->input('region');
            if (in_array($region, LibraryItem::supportedRegions(), true)) {
                $query->whereRegionsMatchOrGlobal($region);
            }
        }

        if ($request->filled('access')) {
            match ($request->input('access')) {
                'free' => $query->where(function ($q) {
                    $q->where('is_premium', false)
                        ->where(function ($priceQuery) {
                            $priceQuery->whereNull('price')->orWhere('price', '<=', 0);
                        });
                }),
                'paid' => $query->where(function ($q) {
                    $q->where('is_premium', true)->orWhere('price', '>', 0);
                }),
                default => null,
            };
        }

        if ($request->boolean('favorites')) {
            $userId = auth()->id();
            if ($userId) {
                $query->whereHas('userAccess', function ($q) use ($userId) {
                    $q->where('user_id', $userId)->where('is_favorite', true);
                });
            }
        }

        match ($request->input('sort', 'newest')) {
            'newest' => $query->orderByDesc('created_at'),
            'oldest' => $query->orderBy('created_at'),
            'popular' => $query->orderByDesc('view_count')->orderByDesc('created_at'),
            'best_sellers' => $query
                ->withCount(['userAccess as purchases_count' => fn ($q) => $q->whereNotNull('purchased_at')])
                ->orderByDesc('purchases_count')
                ->orderByDesc('view_count'),
            'title', 'title_asc' => $query->orderBy('title'),
            'title_desc' => $query->orderByDesc('title'),
            'price_low' => $query->orderByRaw('COALESCE(price, 0) asc')->orderBy('title'),
            'price_high' => $query->orderByRaw('COALESCE(price, 0) desc')->orderBy('title'),
            'random' => $query->inRandomOrder(),
            default => $query->orderByDesc('is_featured')->orderByDesc('created_at'),
        };

        // Add favorite status for current user
        $items = $query->paginate(16)
            ->withQueryString();

        // Add favorite status to each item
        $userId = auth()->id();
        $items->getCollection()->transform(function ($item) use ($userId) {
            $access = $userId ? $item->userAccess()->where('user_id', $userId)->first() : null;
            $item->is_favorite = $access?->is_favorite ?? false;
            $item->has_access = $userId && (bool) $access?->purchased_at;
            $item->reading_progress_percent = $this->readingProgressPercent($access, $item);

            return $item;
        });

        $types = LibraryItem::typeOptionsWithCounts();
        $categories = LibraryCategory::active()->ordered()->get(['id', 'name', 'slug']);
        $authors = LibraryAuthor::query()
            ->whereHas('libraryItems', fn ($q) => $q->active())
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
        $regions = LibraryItem::regionOptions();

        $itemsArray = $items->toArray();

        $groupedItems = $this->buildCategoryGroups($items->getCollection());
        $filters = $request->only(['search', 'category', 'author', 'type', 'region', 'access', 'sort', 'favorites']);
        $filters['sort'] = $request->input('sort', 'newest');

        return Inertia::render('Library/Index', [
            'items' => $itemsArray,
            'categories' => $categories,
            'authors' => $authors,
            'types' => $types,
            'regions' => $regions,
            'regionOptions' => $regions,
            'groupedItems' => $groupedItems,
            'forcedType' => $forcedType,
            'filters' => $filters,
        ]);
    }

    public function ebooks(Request $request): Response
    {
        $request->attributes->set('library_forced_type', 'ebook');

        return $this->index($request->merge(['type' => 'ebook']));
    }

    public function audiobooks(Request $request): Response
    {
        $request->attributes->set('library_forced_type', 'audiobook');

        return $this->index($request->merge(['type' => 'audiobook']));
    }

    public function show(LibraryItem $item): Response
    {
        abort_unless($item->is_active, 404);
        $this->abortIfNotAvailableInUserRegion($item);

        $item->load(['category', 'libraryAuthor']);
        $item->incrementViews();
        if (auth()->check()) {
            $item->recordAccess(auth()->user());
        }

        // Get related items
        $relatedItems = LibraryItem::query()
            ->active()
            ->where('id', '!=', $item->id)
            ->where('category_id', $item->category_id)
            ->with('libraryAuthor')
            ->limit(4)
            ->get();

        // Get user's access info
        $userAccess = auth()->id()
            ? $item->userAccess()->where('user_id', auth()->id())->first()
            : null;
        $hasAccess = (bool) $userAccess?->purchased_at;
        $requiresPaidAccess = $this->requiresPaidAccess($item);

        $stripeConfigured = $this->stripeIsConfigured();

        $manualPaymentsAvailable = $this->manualPaymentsEnabled();
        $stripeSetupNote = $stripeConfigured
            ? null
            : (config('app.debug')
                ? 'Add STRIPE_KEY (publishable) and STRIPE_SECRET from Stripe to enable card checkout. Run php artisan config:clear after changing .env.'
                : ($manualPaymentsAvailable ? null : 'Online checkout is not available right now. Please try again later or contact support.'));

        Log::info('Library show: summary payload prepared', [
            'library_item_id' => $item->id,
            'user_id' => auth()->id(),
            'has_access' => $hasAccess,
            'summary_status' => $item->ai_summary_status,
            'summary_chars' => mb_strlen((string) ($item->ai_summary ?? '')),
            'summary_url_enabled' => $item->type === 'ebook' && $hasAccess,
        ]);

        return Inertia::render('Library/Show', [
            'item' => $item,
            'relatedItems' => $relatedItems,
            'userAccess' => $userAccess,
            'hasAccess' => $hasAccess,
            'requiresPaidAccess' => $requiresPaidAccess,
            'stripeSetupNote' => $stripeSetupNote,
            'libraryPaymentMode' => $stripeConfigured ? 'stripe' : ($manualPaymentsAvailable ? 'manual' : 'unavailable'),
            'manualPaymentPending' => (bool) ($userAccess?->manual_payment_requested_at && ! $userAccess?->purchased_at),
            'mediaUrls' => $hasAccess ? $this->readerMediaUrls($item) : [],
            'progressUrl' => $hasAccess ? route('library.progress', $item) : null,
            'summary' => $item->type === 'ebook' ? $item->ai_summary : null,
            'summaryUrl' => ($item->type === 'ebook' && $hasAccess) ? route('library.summary', $item) : null,
            'summaryStatus' => $item->type === 'ebook' ? $item->ai_summary_status : null,
        ]);
    }

    public function read(LibraryItem $item): Response|RedirectResponse
    {
        abort_unless($item->is_active, 404);
        $this->abortIfNotAvailableInUserRegion($item);

        if (! $this->userHasAccess($item)) {
            return redirect()
                ->route('library.show', $item)
                ->with('error', 'Unlock this title before opening it.');
        }

        $access = $item->recordAccess(auth()->user());

        return Inertia::render('Library/Reader', [
            'item' => $item->load(['category', 'libraryAuthor']),
            'userAccess' => $access,
            'mediaUrl' => route('library.media', $item),
            'mediaUrls' => $this->readerMediaUrls($item),
            'progressUrl' => route('library.progress', $item),
            'summary' => $item->type === 'ebook' ? $item->ai_summary : null,
            'summaryUrl' => $item->type === 'ebook' ? route('library.summary', $item) : null,
            'summaryStatus' => $item->type === 'ebook' ? $item->ai_summary_status : null,
        ]);
    }

    public function myLibrary(Request $request): Response
    {
        $user = $request->user();

        $purchased = LibraryItem::query()
            ->active()
            ->whereHas('userAccess', function ($q) use ($user) {
                $q->where('user_id', $user->id)->whereNotNull('purchased_at');
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->search($request->string('search')->toString());
            })
            ->with([
                'libraryAuthor',
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
            ->paginate(12, ['*'], 'purchased_page')
            ->withQueryString();

        $available = LibraryItem::query()
            ->active()
            ->where(function ($q) use ($user) {
                $q->whereDoesntHave('userAccess', function ($access) use ($user) {
                    $access->where('user_id', $user->id)->whereNotNull('purchased_at');
                });
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->search($request->string('search')->toString());
            })
            ->with(['libraryAuthor', 'category'])
            ->orderByDesc('is_featured')
            ->orderByDesc('created_at')
            ->paginate(12, ['*'], 'available_page')
            ->withQueryString();

        return Inertia::render('Library/MyLibrary', [
            'purchasedItems' => $purchased->toArray(),
            'availableItems' => $available->toArray(),
            'filters' => $request->only(['search', 'tab']),
        ]);
    }

    public function media(Request $request, LibraryItem $item): BinaryFileResponse|StreamedResponse
    {
        abort_unless($item->is_active, 404);
        $this->abortIfNotAvailableInUserRegion($item);
        abort_unless($this->userHasAccess($item), 403);

        return app(LibraryMediaStreamService::class)->deliver(
            $item,
            is_string($request->query('asset')) ? $request->query('asset') : null,
            $request
        );
    }

    public function download(Request $request, LibraryItem $item): RedirectResponse
    {
        abort_unless($item->is_active, 404);
        $this->abortIfNotAvailableInUserRegion($item);

        if (! $this->userHasAccess($item)) {
            return redirect()
                ->route('library.show', $item)
                ->with('error', 'Unlock this title before opening it.');
        }

        return redirect()
            ->route('library.read', $item)
            ->with('info', 'Downloads are turned off for protected titles. You can keep reading or listening here.');
    }

    public function toggleFavorite(LibraryItem $item): RedirectResponse
    {
        $access = $item->userAccess()->firstOrCreate([
            'user_id' => auth()->id(),
        ]);

        $access->toggleFavorite();

        return back()->with('success', $access->is_favorite ? 'Added to favorites!' : 'Removed from favorites.');
    }

    /**
     * Stripe hosted Checkout or manual instructions (Library/ManualPayment.vue).
     */
    public function pay(Request $request, LibraryItem $item): Response|RedirectResponse|SymfonyResponse
    {
        abort_unless($item->is_active, 404);
        $this->abortIfNotAvailableInUserRegion($item);

        $requiresPaidAccess = $this->requiresPaidAccess($item);
        if (! $item->is_premium && ! $requiresPaidAccess) {
            return redirect()
                ->route('library.show', $item)
                ->with('info', 'This item is already available for free.');
        }

        if ($item->price === null || (float) $item->price <= 0) {
            return redirect()
                ->route('library.show', $item)
                ->with('error', 'This item is not available for online purchase.');
        }

        if ($item->userAccess()
            ->where('user_id', auth()->id())
            ->whereNotNull('purchased_at')
            ->exists()) {
            return redirect()
                ->route('library.show', $item)
                ->with('success', 'You already have access to this title.');
        }

        if (! $this->stripeIsConfigured()) {
            if ($this->manualPaymentsEnabled()) {
                return $this->renderManualPaymentPage($item);
            }

            return redirect()
                ->route('library.show', $item)
                ->with('error', config('app.debug')
                    ? 'Stripe checkout is not configured. Add STRIPE_KEY and STRIPE_SECRET, then run php artisan config:clear.'
                    : 'Online checkout is not available right now. Please try again later or contact support.');
        }

        $secret = config('services.stripe.secret');
        Stripe::setApiKey($secret);

        $currency = strtolower((string) ($item->currency ?? 'USD'));
        $unitAmount = (int) round((float) $item->price * 100);

        if ($currency === 'usd' && $unitAmount < 50) {
            return redirect()
                ->route('library.show', $item)
                ->with('error', 'This price is below the minimum for card payments. Please contact support.');
        }

        $description = Str::limit(strip_tags((string) $item->description), 450);
        if ($description === '') {
            $description = 'Digital library item';
        }

        $portal = $request->query('portal') === 'provider' ? 'provider' : 'user';

        try {
            $session = StripeCheckoutSession::create([
                'mode' => 'payment',
                'customer_email' => auth()->user()->email,
                'client_reference_id' => (string) auth()->id(),
                'success_url' => route('library.purchase.return', [], true).'?session_id={CHECKOUT_SESSION_ID}&portal='.$portal,
                'cancel_url' => route('library.purchase.cancel', $item, true),
                'metadata' => [
                    'app' => 'library',
                    'library_item_id' => (string) $item->id,
                    'user_id' => (string) auth()->id(),
                    'portal' => $portal,
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
                'custom_text' => [
                    'submit' => [
                        'message' => 'After payment, you will return here and can read or listen from your library.',
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Stripe library checkout session failed', [
                'library_item_id' => $item->id,
                'message' => $e->getMessage(),
            ]);

            return redirect()
                ->route('library.show', $item)
                ->with('error', config('app.debug')
                    ? 'Payment could not start: '.$e->getMessage()
                    : 'Payment could not start. Please try again or contact support.');
        }

        $checkoutUrl = $session->url;
        if (! is_string($checkoutUrl) || trim($checkoutUrl) === '') {
            return redirect()
                ->route('library.show', $item)
                ->with('error', 'Could not start checkout. Please try again.');
        }

        return Inertia::location($checkoutUrl);
    }

    /**
     * User submits payment reference after paying outside Stripe (bank transfer, PayPal, etc.).
     */
    public function storeManualPayment(Request $request, LibraryItem $item): RedirectResponse
    {
        abort_unless($item->is_active, 404);
        $this->abortIfNotAvailableInUserRegion($item);

        $requiresPaidAccess = $this->requiresPaidAccess($item);
        if (! $item->is_premium && ! $requiresPaidAccess) {
            return redirect()
                ->route('library.show', $item)
                ->with('info', 'This item is already available for free.');
        }

        if ($this->stripeIsConfigured()) {
            return redirect()
                ->route('library.pay', $item)
                ->with('info', 'Use card checkout to complete this purchase.');
        }

        if (! $this->manualPaymentsEnabled()) {
            return redirect()
                ->route('library.show', $item)
                ->with('error', 'Manual payment is not enabled for this title.');
        }

        if ($item->price === null || (float) $item->price <= 0) {
            return redirect()
                ->route('library.show', $item)
                ->with('error', 'This item is not available for purchase.');
        }

        $validated = $request->validate([
            'manual_payment_reference' => ['required', 'string', 'max:255'],
            'manual_payment_note' => ['nullable', 'string', 'max:2000'],
        ]);

        return DB::transaction(function () use ($item, $validated) {
            /** @var LibraryUserAccess $access */
            $access = $item->userAccess()->firstOrCreate(
                [
                    'user_id' => auth()->id(),
                ],
                []
            );

            $access->refresh();

            if ($access->purchased_at !== null) {
                return redirect()
                    ->route('library.show', $item)
                    ->with('success', 'You already have access to this title.');
            }

            $access->update([
                'manual_payment_requested_at' => now(),
                'manual_payment_reference' => $validated['manual_payment_reference'],
                'manual_payment_note' => $validated['manual_payment_note'] ?? null,
            ]);

            return redirect()
                ->route('library.show', $item)
                ->with('success', 'Thanks — we received your payment details. An administrator will verify and unlock your access.');
        });
    }

    public function purchaseReturn(Request $request, FulfillLibraryStripeCheckout $fulfill): RedirectResponse
    {
        $sessionId = $request->query('session_id');
        if (! is_string($sessionId) || $sessionId === '') {
            return redirect()
                ->route('library.index')
                ->with('error', 'Missing payment confirmation.');
        }

        $portal = $request->query('portal') === 'provider' ? 'provider' : null;

        $secret = config('services.stripe.secret');
        if (! is_string($secret) || $secret === '') {
            return redirect()
                ->route('library.index')
                ->with('error', 'Payments are not configured.');
        }

        try {
            Stripe::setApiKey($secret);
            $session = StripeCheckoutSession::retrieve($sessionId);
        } catch (\Throwable $e) {
            Log::error('Stripe library checkout return failed', [
                'session_id' => $sessionId,
                'message' => $e->getMessage(),
            ]);

            return redirect()
                ->route('library.index')
                ->with('error', config('app.debug')
                    ? 'Could not verify payment: '.$e->getMessage()
                    : 'Could not verify payment. Please contact support.');
        }

        $metadataUserId = (int) ($session->metadata['user_id'] ?? 0);
        if ($metadataUserId !== (int) auth()->id()) {
            abort(403);
        }

        $fulfill($session);

        $purchasedIds = [];
        $rawMulti = $session->metadata['library_item_ids'] ?? null;
        if (is_string($rawMulti) && trim($rawMulti) !== '') {
            $purchasedIds = array_values(array_unique(array_filter(array_map('intval', explode(',', $rawMulti)))));
        }
        if ($purchasedIds === []) {
            $single = (int) ($session->metadata['library_item_id'] ?? 0);
            if ($single > 0) {
                $purchasedIds = [$single];
            }
        }

        if ($purchasedIds !== []) {
            $current = $this->getLibraryCartIds();
            $this->setLibraryCartIds(array_values(array_diff($current, $purchasedIds)));
        }

        if (count($purchasedIds) > 1) {
            return redirect()
                ->route($portal === 'provider' ? 'provider.library.index' : 'library.index')
                ->with('success', 'Payment successful. Your titles are ready in your library.');
        }

        $itemId = $purchasedIds[0] ?? (int) ($session->metadata['library_item_id'] ?? 0);
        $item = LibraryItem::query()->whereKey($itemId)->first();

        if ($item) {
            return redirect()
                ->route($portal === 'provider' ? 'provider.library.show' : 'library.show', $item)
                ->with('success', 'Payment successful. You can read or listen now.');
        }

        return redirect()
            ->route($portal === 'provider' ? 'provider.library.index' : 'library.index')
            ->with('success', 'Payment successful.');
    }

    public function purchaseCancel(LibraryItem $item): RedirectResponse
    {
        abort_unless($item->is_active, 404);
        $this->abortIfNotAvailableInUserRegion($item);

        return redirect()
            ->route('library.show', $item)
            ->with('info', 'Checkout was cancelled. You can try again when you are ready.');
    }

    /**
     * Free library items only: grants access and opens the reader/player.
     * Premium titles use Stripe embedded checkout from the pay page.
     */
    public function purchase(LibraryItem $item): RedirectResponse
    {
        abort_unless($item->is_active, 404);
        $this->abortIfNotAvailableInUserRegion($item);

        if ($this->requiresPaidAccess($item)) {
            return redirect()
                ->route('library.pay', $item)
                ->with('info', 'Use card checkout to unlock this item.');
        }

        $access = $item->userAccess()->firstOrCreate([
            'user_id' => auth()->id(),
        ]);

        if (! $access->purchased_at) {
            $access->update([
                'purchased_at' => now(),
                'purchase_amount' => 0,
                'purchase_currency' => $item->currency ?? 'USD',
            ]);
        }

        return redirect()
            ->route('library.read', $item)
            ->with('success', 'Added to your library.');
    }

    public function updateProgress(Request $request, LibraryItem $item): JsonResponse|RedirectResponse
    {
        abort_unless($item->is_active, 404);
        $this->abortIfNotAvailableInUserRegion($item);
        abort_unless($this->userHasAccess($item), 403);

        $validated = $request->validate([
            'mode' => ['required', 'string', 'in:reading,audio'],
            'progress' => ['required', 'array'],
            'progress.page' => ['nullable', 'integer', 'min:1'],
            'progress.total_pages' => ['nullable', 'integer', 'min:1'],
            'progress.position' => ['nullable', 'numeric', 'min:0'],
            'progress.duration' => ['nullable', 'numeric', 'min:0'],
            'progress.percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $access = $item->userAccess()->where('user_id', auth()->id())->firstOrFail();

        $access->updateProgress($validated['progress'], $validated['mode']);

        if ($request->expectsJson()) {
            return response()->json([
                'progress' => $access->fresh()->progress,
            ]);
        }

        return back();
    }

    public function summary(Request $request, LibraryItem $item): JsonResponse|RedirectResponse
    {
        abort_unless($item->is_active, 404);
        $this->abortIfNotAvailableInUserRegion($item);
        abort_unless($item->type === 'ebook', 404);
        abort_unless($this->userHasAccess($item), 403);

        $fresh = LibraryItem::query()->whereKey($item->id)->firstOrFail();
        Log::info('Library AI summary: user requested summary retrieval', [
            'library_item_id' => $fresh->id,
            'user_id' => auth()->id(),
            'has_summary' => (bool) $fresh->ai_summary,
            'status' => $fresh->ai_summary_status,
        ]);

        $message = null;
        if (! $fresh->ai_summary) {
            $message = 'Summary not available yet. It is generated when admin uploads this ebook.';
        }

        if ($request->expectsJson()) {
            Log::info('Library AI summary: user summary response sent', [
                'library_item_id' => $fresh->id,
                'user_id' => auth()->id(),
                'status' => $fresh->ai_summary_status,
                'summary_chars' => mb_strlen((string) ($fresh->ai_summary ?? '')),
                'has_message' => (bool) $message,
            ]);

            return response()->json([
                'summary' => $fresh->ai_summary,
                'status' => $fresh->ai_summary_status,
                'message' => $message,
                'generated_at' => optional($fresh->ai_summary_generated_at)?->toIso8601String(),
            ]);
        }

        return $message ? back()->with('info', $message) : back();
    }

    private function stripeIsConfigured(): bool
    {
        return StripeConfig::checkoutConfigured();
    }

    private function requiresPaidAccess(LibraryItem $item): bool
    {
        return $item->is_premium || (float) ($item->price ?? 0) > 0;
    }

    private function userHasAccess(LibraryItem $item): bool
    {
        $userId = auth()->id();
        if (! $userId) {
            return false;
        }

        return $item->userAccess()
            ->where('user_id', $userId)
            ->whereNotNull('purchased_at')
            ->exists();
    }

    private function readerMediaUrls(LibraryItem $item): array
    {
        if ($item->type === 'ebook') {
            $urls = [
                'pdf' => route('library.media', $item),
            ];

            if ($item->audio_file_path) {
                $urls['audio'] = route('library.media', ['item' => $item, 'asset' => 'audio']);
            }

            return $urls;
        }

        return [
            'audio' => route('library.media', $item),
        ];
    }

    private function manualPaymentInstructions(): string
    {
        $custom = config('manual_payment.instructions');

        return is_string($custom) && $custom !== ''
            ? $custom
            : (string) config('manual_payment.default_instructions');
    }

    private function renderManualPaymentPage(LibraryItem $item): Response|RedirectResponse
    {
        if (! $this->manualPaymentsEnabled()) {
            return redirect()
                ->route('library.show', $item)
                ->with('error', 'Manual payment is not enabled for this title.');
        }

        $instructions = $this->manualPaymentInstructions();
        if ($instructions === '') {
            return redirect()
                ->route('library.show', $item)
                ->with('error', config('app.debug')
                    ? 'Set MANUAL_PAYMENT_INSTRUCTIONS in .env (or configure Stripe), then run php artisan config:clear.'
                    : 'Payments are not configured yet. Please contact support.');
        }

        $access = $item->userAccess()->where('user_id', auth()->id())->first();
        $pending = $access && $access->manual_payment_requested_at && ! $access->purchased_at;

        return Inertia::render('Library/ManualPayment', [
            'item' => [
                'title' => $item->title,
                'slug' => $item->slug,
                'price' => $item->price,
                'currency' => $item->currency ?? 'USD',
                'type' => $item->type,
            ],
            'instructions' => $instructions,
            'pending' => $pending,
            'submitted' => $pending ? [
                'manual_payment_reference' => $access->manual_payment_reference,
                'manual_payment_note' => $access->manual_payment_note,
                'manual_payment_requested_at' => $access->manual_payment_requested_at?->toIso8601String(),
            ] : null,
            'formDefaults' => [
                'manual_payment_reference' => $access?->manual_payment_reference ?? '',
                'manual_payment_note' => $access?->manual_payment_note ?? '',
            ],
        ]);
    }

    private function manualPaymentsEnabled(): bool
    {
        return (bool) config('manual_payment.enabled');
    }

    private function readingProgressPercent(?LibraryUserAccess $access, LibraryItem $item): ?int
    {
        if (! $access?->progress || ! is_array($access->progress)) {
            return null;
        }

        $progress = $access->progress;
        $modeProgress = $item->type === 'audiobook'
            ? data_get($progress, 'audio', $progress)
            : data_get($progress, 'reading', $progress);

        if (! is_array($modeProgress)) {
            return null;
        }

        if ($item->type === 'ebook' && isset($modeProgress['page'])) {
            $page = (int) $modeProgress['page'];
            $total = (int) ($modeProgress['total_pages'] ?? 0);
            if ($total < 1) {
                $total = 100;
            }

            return (int) max(0, min(100, round(($page / $total) * 100)));
        }

        if ($item->type === 'audiobook' && isset($modeProgress['position'], $modeProgress['duration'])) {
            $position = (float) $modeProgress['position'];
            $duration = max(1, (float) $modeProgress['duration']);

            return (int) max(0, min(100, round(($position / $duration) * 100)));
        }

        if (isset($modeProgress['percentage'])) {
            return (int) max(0, min(100, round((float) $modeProgress['percentage'])));
        }

        return null;
    }

    private function buildCategoryGroups(Collection $items): array
    {
        $groups = $items
            ->groupBy(fn ($item) => $item->category?->slug ?? 'uncategorized')
            ->map(function ($groupItems, $slug) {
                $first = $groupItems->first();

                return [
                    'slug' => $slug,
                    'name' => $first->category?->name ?? 'Uncategorized',
                    'items' => $groupItems->values(),
                ];
            })
            ->values()
            ->all();

        return $groups;
    }
}
