<?php

namespace App\Http\Controllers\Provider;

use App\Actions\Library\QueueLibraryEbookSummary;
use App\Http\Controllers\Controller;
use App\Http\Controllers\LibraryController as SiteLibraryController;
use App\Models\EbookCoupon;
use App\Models\LibraryItem;
use App\Models\LibraryUserAccess;
use App\Support\EbookShareCampaignPresenter;
use App\Support\StripeConfig;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class LibraryController extends Controller
{
    public function cart(SiteLibraryController $library): Response
    {
        return Inertia::render('Provider/Library/Cart', $library->cartPayload());
    }

    public function index(Request $request): Response
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
            ->paginate(100, ['*'], 'purchased_page')
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
            ->paginate(100, ['*'], 'available_page')
            ->withQueryString();

        return Inertia::render('Provider/Library/Index', [
            'purchasedItems' => $purchased->toArray(),
            'availableItems' => $available->toArray(),
            'filters' => $request->only(['search', 'tab']),
        ]);
    }

    private function regionForCurrentUser(): ?string
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        return LibraryItem::regionForCountry($user->country ?? null);
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

    private function abortIfNotAvailableInUserRegion(LibraryItem $item): void
    {
        abort_unless($this->itemAvailableInUserRegion($item), 404);
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

    public function show(LibraryItem $item): Response
    {
        abort_unless($item->is_active, 404);
        $this->abortIfNotAvailableInUserRegion($item);

        $item->load(['category', 'libraryAuthor']);
        $item->incrementViews();
        if (auth()->check()) {
            $item->recordAccess(auth()->user());
        }

        $relatedItems = LibraryItem::query()
            ->active()
            ->where('id', '!=', $item->id)
            ->when($item->category_id, fn ($q) => $q->where('category_id', $item->category_id))
            ->with('libraryAuthor')
            ->limit(4)
            ->get();

        $userAccess = auth()->id()
            ? $item->userAccess()->where('user_id', auth()->id())->first()
            : null;
        $hasAccess = (bool) $userAccess?->purchased_at;
        $requiresPaidAccess = $this->requiresPaidAccess($item);
        $stripeConfigured = StripeConfig::checkoutConfigured();
        $manualPaymentsAvailable = (bool) config('manual_payment.enabled');

        if ($item->type === 'ebook') {
            app(QueueLibraryEbookSummary::class)($item);
            $item = $item->fresh() ?? $item;
        }

        Log::info('Provider library show payload prepared', [
            'library_item_id' => $item->id,
            'user_id' => auth()->id(),
            'has_access' => $hasAccess,
        ]);

        return Inertia::render('Provider/Library/Show', [
            'item' => $item,
            'relatedItems' => $relatedItems,
            'userAccess' => $userAccess,
            'hasAccess' => $hasAccess,
            'requiresPaidAccess' => $requiresPaidAccess,
            // Provider portal uses the same checkout + protected media endpoints.
            'libraryPaymentMode' => $stripeConfigured ? 'stripe' : ($manualPaymentsAvailable ? 'manual' : 'unavailable'),
            'manualPaymentPending' => (bool) ($userAccess?->manual_payment_requested_at && ! $userAccess?->purchased_at),
            'mediaUrls' => $hasAccess ? $this->readerMediaUrls($item) : [],
            'progressUrl' => $hasAccess ? route('library.progress', $item) : null,
            'summary' => $item->type === 'ebook' ? $item->ai_summary : null,
            'summaryUrl' => $item->type === 'ebook' ? route('library.summary', $item) : null,
            'summaryStatus' => $item->type === 'ebook' ? $item->ai_summary_status : null,
            'shareCampaign' => EbookShareCampaignPresenter::forUser(auth()->user()),
            'activeEbookCoupon' => $this->activeEbookCouponPayload(),
        ]);
    }

    /**
     * @return array{code: string, issued_for: string}|null
     */
    private function activeEbookCouponPayload(): ?array
    {
        if (! auth()->check()) {
            return null;
        }

        $coupon = EbookCoupon::activeCouponForUser((int) auth()->id());
        if (! $coupon) {
            return null;
        }

        return [
            'code' => $coupon->code,
            'issued_for' => $coupon->issued_for,
        ];
    }

    public function read(LibraryItem $item): Response|RedirectResponse
    {
        abort_unless($item->is_active, 404);
        $this->abortIfNotAvailableInUserRegion($item);

        if (! $this->userHasAccess($item)) {
            return redirect()
                ->route('provider.library.show', $item)
                ->with('error', 'Unlock this title before opening it.');
        }

        $access = $item->recordAccess(auth()->user());

        return Inertia::render('Provider/Library/Reader', [
            'item' => $item->load(['category', 'libraryAuthor']),
            'userAccess' => $access,
            'mediaUrl' => route('library.media', $item),
            'mediaUrls' => $this->readerMediaUrls($item),
            'progressUrl' => route('library.progress', $item),
            'summary' => $item->type === 'ebook' ? $item->ai_summary : null,
            'summaryUrl' => $item->type === 'ebook' ? route('library.summary', $item) : null,
        ]);
    }

    public function shareCampaign(Request $request): Response
    {
        return Inertia::render('Provider/Library/ShareCampaign', [
            'campaign' => EbookShareCampaignPresenter::forUser($request->user()),
        ]);
    }
}
