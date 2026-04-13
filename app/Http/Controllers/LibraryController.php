<?php

namespace App\Http\Controllers;

use App\Actions\Library\FulfillLibraryStripeCheckout;
use App\Models\LibraryCategory;
use App\Models\LibraryItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Stripe;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LibraryController extends Controller
{
    public function index(Request $request): Response
    {
        $query = LibraryItem::query()
            ->with('category')
            ->active();

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

        if ($request->boolean('favorites')) {
            $userId = auth()->id();
            if ($userId) {
                $query->whereHas('userAccess', function ($q) use ($userId) {
                    $q->where('user_id', $userId)->where('is_favorite', true);
                });
            }
        }

        // Add favorite status for current user
        $items = $query->orderByDesc('is_featured')
            ->orderByDesc('created_at')
            ->paginate(16)
            ->withQueryString();

        // Add favorite status to each item
        $userId = auth()->id();
        $items->getCollection()->transform(function ($item) use ($userId) {
            $access = $userId ? $item->userAccess()->where('user_id', $userId)->first() : null;
            $item->is_favorite = $access?->is_favorite ?? false;
            $item->has_access = $userId && (bool) $access?->purchased_at;

            return $item;
        });

        $types = LibraryItem::typeOptionsWithCounts();
        $categories = LibraryCategory::active()->ordered()->get(['id', 'name', 'slug']);

        $itemsArray = $items->toArray();

        $groupedItems = $this->buildCategoryGroups($items->getCollection());

        return Inertia::render('Library/Index', [
            'items' => $itemsArray,
            'categories' => $categories,
            'types' => $types,
            'groupedItems' => $groupedItems,
            'filters' => $request->only(['search', 'category', 'type', 'favorites']),
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

        $item->load('category');
        $item->incrementViews();
        if (auth()->check()) {
            $item->recordAccess(auth()->user());
        }

        // Get related items
        $relatedItems = LibraryItem::query()
            ->active()
            ->where('id', '!=', $item->id)
            ->where('category_id', $item->category_id)
            ->limit(4)
            ->get();

        // Get user's access info
        $userAccess = auth()->id()
            ? $item->userAccess()->where('user_id', auth()->id())->first()
            : null;
        $hasAccess = (bool) $userAccess?->purchased_at;

        $stripeSecret = config('services.stripe.secret');
        $stripeKey = config('services.stripe.key');
        $stripeConfigured = is_string($stripeSecret) && $stripeSecret !== ''
            && is_string($stripeKey) && $stripeKey !== '';

        $stripeSetupNote = $stripeConfigured
            ? null
            : (config('app.debug')
                ? 'Add STRIPE_KEY (publishable) and STRIPE_SECRET from Stripe (test keys are fine on local). If you already added them, run php artisan config:clear so Laravel reloads .env.'
                : 'Card payments are not available right now. Please try again later or contact support.');

        return Inertia::render('Library/Show', [
            'item' => $item,
            'relatedItems' => $relatedItems,
            'userAccess' => $userAccess,
            'hasAccess' => $hasAccess,
            'stripeSetupNote' => $stripeSetupNote,
        ]);
    }

    public function download(LibraryItem $item): StreamedResponse|RedirectResponse
    {
        abort_unless($item->is_active, 404);

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

        // Record the download
        $item->incrementDownloads();
        if (auth()->check()) {
            $item->recordAccess(auth()->user());
        }

        $fileDisk = $item->resolveLibraryFileDisk();
        if ($fileDisk === null) {
            return redirect()
                ->route('library.show', $item)
                ->with('error', 'File not found. Please contact support.');
        }

        return Storage::disk($fileDisk)->download(
            $item->file_path,
            $item->file_name ?? basename($item->file_path)
        );
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
     * Embedded Stripe Checkout — card UI loads on Library/Payment.vue.
     */
    public function pay(LibraryItem $item): Response|RedirectResponse
    {
        abort_unless($item->is_active && $item->is_premium, 404);

        $secret = config('services.stripe.secret');
        $publishable = config('services.stripe.key');
        if (! is_string($secret) || $secret === '' || ! is_string($publishable) || $publishable === '') {
            $hint = config('app.debug')
                ? 'Add STRIPE_KEY (or STRIPE_PUBLISHABLE_KEY) and STRIPE_SECRET (or STRIPE_SECRET_KEY) to .env, then run php artisan config:clear.'
                : 'Card payments are not configured yet. Please contact support.';

            return redirect()
                ->route('library.show', $item)
                ->with('error', $hint);
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

        $session = StripeCheckoutSession::create([
            'ui_mode' => 'embedded',
            'mode' => 'payment',
            'customer_email' => auth()->user()->email,
            'client_reference_id' => (string) auth()->id(),
            'return_url' => route('library.purchase.return', [], true).'?session_id={CHECKOUT_SESSION_ID}',
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
            'custom_text' => [
                'submit' => [
                    'message' => 'After payment, you will return here and can download from the library.',
                ],
            ],
        ]);

        $clientSecret = $session->client_secret;
        if (! is_string($clientSecret) || $clientSecret === '') {
            return redirect()
                ->route('library.show', $item)
                ->with('error', 'Could not start checkout. Please try again.');
        }

        return Inertia::render('Library/Payment', [
            'item' => [
                'title' => $item->title,
                'slug' => $item->slug,
                'price' => $item->price,
                'currency' => $item->currency ?? 'USD',
                'type' => $item->type,
            ],
            'checkoutClientSecret' => $clientSecret,
            'stripePublishableKey' => $publishable,
        ]);
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

        return redirect()
            ->route('library.show', $item)
            ->with('info', 'Checkout was cancelled. You can try again when you are ready.');
    }

    /**
     * Free library items only: grants access and starts download.
     * Premium titles use Stripe embedded checkout from the pay page.
     */
    public function purchase(LibraryItem $item): RedirectResponse
    {
        abort_unless($item->is_active, 404);

        if ($item->is_premium) {
            return redirect()
                ->route('library.show', $item)
                ->with('info', 'Use Purchase on this page to pay with a card.');
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
