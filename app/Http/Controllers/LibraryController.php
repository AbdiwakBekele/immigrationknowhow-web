<?php

namespace App\Http\Controllers;

use App\Actions\Library\FulfillLibraryStripeCheckout;
use App\Models\LibraryAuthor;
use App\Models\LibraryCategory;
use App\Models\LibraryItem;
use App\Models\LibraryUserAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Stripe;
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
        $region = $this->regionForCurrentUser();
        if ($region === null) {
            return;
        }

        $regions = $item->regions ?? null;
        if (! is_array($regions) || count($regions) === 0) {
            return;
        }

        abort_unless(in_array($region, $regions, true), 404);
    }

    public function index(Request $request): Response
    {
        $query = LibraryItem::query()
            ->with(['category', 'libraryAuthor'])
            ->active();

        $query->availableInRegion($this->regionForCurrentUser());

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

        if ($request->filled('type')) {
            $type = $request->input('type');
            if (in_array($type, LibraryItem::supportedTypes(), true)) {
                $query->where('type', $type);
            }
        }

        if ($request->filled('region')) {
            $region = $request->string('region')->toString();
            if (in_array($region, LibraryItem::supportedRegions(), true)) {
                $query->whereRegionsMatchOrGlobal($region);
            }
        }

        if ($request->filled('author')) {
            $author = LibraryAuthor::query()->where('slug', $request->input('author'))->first();
            if ($author) {
                $query->where('author_id', $author->id);
            }
        }

        if ($request->boolean('favorites')) {
            $userId = auth()->id();
            if ($userId) {
                $query->whereHas('userAccess', function ($q) use ($userId) {
                    $q->where('user_id', $userId)->where('is_favorite', true);
                });
            }
        }

        $sort = $request->input('sort', 'newest');
        if ($sort === 'oldest') {
            $query->orderBy('created_at');
        } elseif ($sort === 'title_asc') {
            $query->orderBy('title');
        } elseif ($sort === 'title_desc') {
            $query->orderByDesc('title');
        } else {
            $query->orderByDesc('is_featured')->orderByDesc('created_at');
        }

        // Add favorite status for current user
        $items = $query->paginate(16)->withQueryString();

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

        $userRegion = $this->regionForCurrentUser();
        $authors = LibraryAuthor::query()
            ->whereHas('libraryItems', function ($q) use ($userRegion) {
                $q->active()->availableInRegion($userRegion);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $itemsArray = $items->toArray();

        return Inertia::render('Library/Index', [
            'items' => $itemsArray,
            'categories' => $categories,
            'types' => $types,
            'authors' => $authors,
            'regionOptions' => LibraryItem::regionOptions(),
            'filters' => $request->only(['search', 'category', 'type', 'favorites', 'region', 'author', 'sort']),
        ]);
    }

    public function ebooks(Request $request): Response
    {
        return $this->index($request->merge(['type' => 'ebook']));
    }

    public function audiobooks(Request $request): Response
    {
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
        $requiresPaidAccess = in_array($item->type, ['audiobook', 'video'], true);

        $stripeConfigured = $this->stripeIsConfigured();

        $stripeSetupNote = $stripeConfigured
            ? null
            : ($this->manualPaymentInstructions() !== ''
                ? null
                : (config('app.debug')
                    ? 'Add STRIPE_KEY (publishable) and STRIPE_SECRET from Stripe, or set MANUAL_PAYMENT_INSTRUCTIONS for manual payments. Run php artisan config:clear after changing .env.'
                    : 'Online checkout is not available right now. Please try again later or contact support.'));

        return Inertia::render('Library/Show', [
            'item' => $item,
            'relatedItems' => $relatedItems,
            'userAccess' => $userAccess,
            'hasAccess' => $hasAccess,
            'requiresPaidAccess' => $requiresPaidAccess,
            'stripeSetupNote' => $stripeSetupNote,
            'libraryPaymentMode' => $stripeConfigured ? 'stripe' : 'manual',
            'manualPaymentPending' => (bool) ($userAccess?->manual_payment_requested_at && ! $userAccess?->purchased_at),
        ]);
    }

    public function download(Request $request, LibraryItem $item): StreamedResponse|RedirectResponse
    {
        abort_unless($item->is_active, 404);
        $this->abortIfNotAvailableInUserRegion($item);

        $userId = auth()->id();
        $hasPurchased = $userId
            ? $item->userAccess()
                ->where('user_id', $userId)
                ->whereNotNull('purchased_at')
                ->exists()
            : false;

        if (! $hasPurchased) {
            return redirect()
                ->route('library.show', $item)
                ->with('error', 'Purchase this item before downloading.');
        }

        $wantAudioCompanion = $request->query('asset') === 'audio';

        if ($wantAudioCompanion) {
            abort_unless($item->type === 'ebook' && $item->audio_file_path, 404);
            $fileDisk = $item->resolveAudioFileDisk();
            $path = $item->audio_file_path;
            $downloadName = $item->audio_file_name ?? basename((string) $path);
        } else {
            $fileDisk = $item->resolveLibraryFileDisk();
            $path = $item->file_path;
            $downloadName = $item->file_name ?? basename((string) $path);
        }

        // Record the download
        $item->incrementDownloads();
        if (auth()->check()) {
            $item->recordAccess(auth()->user());
        }

        if ($fileDisk === null || ! $path) {
            return redirect()
                ->route('library.show', $item)
                ->with('error', 'File not found. Please contact support.');
        }

        return Storage::disk($fileDisk)->download($path, $downloadName);
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
     * Stripe hosted Checkout (same flow as provider subscriptions) or manual instructions (Library/ManualPayment.vue).
     */
    public function pay(LibraryItem $item): Response|RedirectResponse
    {
        abort_unless($item->is_active, 404);
        $this->abortIfNotAvailableInUserRegion($item);

        $requiresPaidAccess = in_array($item->type, ['audiobook', 'video'], true);
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
            return $this->renderManualPaymentPage($item);
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

        try {
            $session = StripeCheckoutSession::create([
                'mode' => 'payment',
                'customer_email' => auth()->user()->email,
                'client_reference_id' => (string) auth()->id(),
                'success_url' => route('library.purchase.return', [], true).'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('library.purchase.cancel', $item, true),
                'metadata' => [
                    'app' => 'library',
                    'library_item_id' => (string) $item->id,
                    'user_id' => (string) auth()->id(),
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

        $requiresPaidAccess = in_array($item->type, ['audiobook', 'video'], true);
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
                ->with('success', 'Thanks — we received your payment details. An administrator will verify and unlock your download.');
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

        $secret = config('services.stripe.secret');
        if (! is_string($secret) || $secret === '') {
            return redirect()
                ->route('library.index')
                ->with('error', 'Payments are not configured.');
        }

        Stripe::setApiKey($secret);
        $session = StripeCheckoutSession::retrieve($sessionId);

        $metadataUserId = (int) ($session->metadata['user_id'] ?? 0);
        if ($metadataUserId !== (int) auth()->id()) {
            abort(403);
        }

        $fulfill($session);

        $itemId = (int) ($session->metadata['library_item_id'] ?? 0);
        $item = LibraryItem::query()->whereKey($itemId)->first();

        if ($item) {
            return redirect()
                ->route('library.show', $item)
                ->with('success', 'Payment successful. You can download your file below.');
        }

        return redirect()
            ->route('library.index')
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
     * Free library items only: grants access and starts download.
     * Premium titles use Stripe hosted Checkout from the pay route.
     */
    public function purchase(LibraryItem $item): RedirectResponse
    {
        abort_unless($item->is_active, 404);
        $this->abortIfNotAvailableInUserRegion($item);

        if ($item->is_premium || in_array($item->type, ['audiobook', 'video'], true)) {
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
            ->route('library.download', $item)
            ->with('success', 'Added to your library. Your download will start shortly.');
    }

    public function updateProgress(Request $request, LibraryItem $item): RedirectResponse
    {
        $this->abortIfNotAvailableInUserRegion($item);
        $validated = $request->validate([
            'progress' => ['required', 'array'],
            'progress.page' => ['nullable', 'integer', 'min:0'],
            'progress.position' => ['nullable', 'integer', 'min:0'],
            'progress.percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $access = $item->userAccess()->firstOrCreate([
            'user_id' => auth()->id(),
        ]);

        $access->updateProgress($validated['progress']);

        return back();
    }

    private function stripeIsConfigured(): bool
    {
        $secret = config('services.stripe.secret');
        $publishable = config('services.stripe.key');

        return is_string($secret) && $secret !== ''
            && is_string($publishable) && $publishable !== '';
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

    private function readingProgressPercent(?LibraryUserAccess $access, LibraryItem $item): ?int
    {
        if (! $access?->progress || ! is_array($access->progress)) {
            return null;
        }

        $p = $access->progress;

        if ($item->type === 'ebook' && isset($p['page'])) {
            $page = (int) $p['page'];
            $total = (int) ($item->page_count ?? 0);
            if ($total < 1) {
                $total = 100;
            }

            return (int) max(0, min(100, round(($page / $total) * 100)));
        }

        if ($item->type === 'audiobook' && isset($p['position']) && $item->duration_seconds) {
            $pos = (int) $p['position'];
            $dur = max(1, (int) $item->duration_seconds);

            return (int) max(0, min(100, round(($pos / $dur) * 100)));
        }

        if (isset($p['percentage'])) {
            return (int) max(0, min(100, round((float) $p['percentage'])));
        }

        return null;
    }
}
