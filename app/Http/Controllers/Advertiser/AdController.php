<?php

namespace App\Http\Controllers\Advertiser;

use App\Actions\Advertiser\FulfillAdvertiserStripeCheckout;
use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\AdPayment;
use App\Support\StripeConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Stripe;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AdController extends Controller
{
    public function index(Request $request): Response
    {
        $user = auth()->user();
        $ads = Ad::query()
            ->where('user_id', $user->id)
            ->latest()
            ->get()
            ->map(fn (Ad $ad) => $this->toAdPayload($ad));

        return Inertia::render('Advertiser/Ads/Index', [
            'ads' => $ads,
            'adPostingPrice' => $this->adPricingPayload(),
            'adsRouteNamePrefix' => $this->adsRouteNamePrefix($request),
            'adPortal' => $this->adPortalPayload($request),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Advertiser/Ads/Create', [
            'adPostingPrice' => $this->adPricingPayload(),
            'adsRouteNamePrefix' => $this->adsRouteNamePrefix($request),
            'adPortal' => $this->adPortalPayload($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateAd($request);
        $priceCents = $this->defaultPriceCents();
        $imageUrl = $this->resolveImageUrl($request, $validated);
        $requireApproval = (bool) config('ads.require_admin_approval', true);

        if ($priceCents > 0) {
            $status = 'pending_payment';
            $paidAt = null;
            $publishedAt = null;
            $success = 'Ad created. Review and complete payment to publish it.';
        } elseif ($requireApproval) {
            $status = 'pending_approval';
            $paidAt = now();
            $publishedAt = null;
            $success = 'Ad submitted. It will appear publicly after an administrator approves it.';
        } else {
            $status = 'published';
            $paidAt = now();
            $publishedAt = now();
            $success = 'Ad created and published.';
        }

        $ad = Ad::query()->create([
            'user_id' => $request->user()->id,
            ...$validated,
            'image_url' => $imageUrl,
            'status' => $status,
            'price_cents' => $priceCents,
            'currency' => $this->defaultCurrency(),
            'paid_at' => $paidAt,
            'published_at' => $publishedAt,
        ]);

        $adsRouteNamePrefix = $this->adsRouteNamePrefix($request);
        $redirectRoute = $priceCents > 0 ? "{$adsRouteNamePrefix}.pay" : "{$adsRouteNamePrefix}.edit";

        return redirect()
            ->route($redirectRoute, $ad)
            ->with('success', $success);
    }

    public function edit(Request $request, Ad $ad): Response
    {
        $this->authorizeAd($ad);

        return Inertia::render('Advertiser/Ads/Edit', [
            'ad' => $this->toAdPayload($ad),
            'adPostingPrice' => $this->adPricingPayload(),
            'publicUrl' => URL::route('ads.public.show', ['ad' => $ad->uuid]),
            'adsRouteNamePrefix' => $this->adsRouteNamePrefix($request),
            'adPortal' => $this->adPortalPayload($request),
        ]);
    }

    public function pay(Request $request, Ad $ad): Response|RedirectResponse
    {
        $this->authorizeAd($ad);
        $adsRouteNamePrefix = $this->adsRouteNamePrefix($request);

        if ($ad->status === 'published') {
            return redirect()->route("{$adsRouteNamePrefix}.edit", $ad)->with('info', 'This ad is already published.');
        }

        if ($ad->status === 'pending_approval') {
            return redirect()->route("{$adsRouteNamePrefix}.edit", $ad)->with('info', 'This ad is awaiting administrator approval.');
        }

        if ($ad->status === 'rejected') {
            return redirect()->route("{$adsRouteNamePrefix}.edit", $ad)->with('info', 'This ad was not approved. Update it and resubmit for review if available.');
        }

        if ($ad->status !== 'pending_payment') {
            return redirect()->route("{$adsRouteNamePrefix}.edit", $ad)->with('info', 'Payment is not required for this ad in its current state.');
        }

        return Inertia::render('Advertiser/Ads/Pay', [
            'ad' => $this->toAdPayload($ad),
            'adPostingPrice' => $this->adPricingPayload(),
            'publicUrl' => URL::route('ads.public.show', ['ad' => $ad->uuid]),
            'adsRouteNamePrefix' => $adsRouteNamePrefix,
            'adPortal' => $this->adPortalPayload($request),
        ]);
    }

    public function update(Request $request, Ad $ad): RedirectResponse
    {
        $this->authorizeAd($ad);
        $validated = $this->validateAd($request);
        $imageUrl = $this->resolveImageUrl($request, $validated, $ad->image_url);

        $ad->update([
            ...$validated,
            'image_url' => $imageUrl,
        ]);

        return back()->with('success', 'Ad updated.');
    }

    public function destroy(Request $request, Ad $ad): RedirectResponse
    {
        $this->authorizeAd($ad);
        $ad->delete();

        return redirect()->route($this->adsRouteName($request, 'index'))->with('success', 'Ad deleted.');
    }

    public function resubmit(Request $request, Ad $ad): RedirectResponse
    {
        $this->authorizeAd($ad);

        abort_unless($ad->status === 'rejected', 422);

        $ad->update([
            'status' => 'pending_approval',
            'meta' => array_merge($ad->meta ?? [], [
                'resubmitted_at' => now()->toIso8601String(),
            ]),
        ]);

        return back()->with('success', 'Ad resubmitted for review.');
    }

    public function checkout(Request $request, Ad $ad): Response|RedirectResponse|SymfonyResponse
    {
        $this->authorizeAd($ad);
        $adsRouteNamePrefix = $this->adsRouteNamePrefix($request);

        if ($ad->status === 'published') {
            return redirect()->route("{$adsRouteNamePrefix}.edit", $ad)->with('info', 'This ad is already published.');
        }

        if ($ad->status === 'pending_approval') {
            return redirect()->route("{$adsRouteNamePrefix}.edit", $ad)->with('info', 'This ad is awaiting administrator approval.');
        }

        if ($ad->status !== 'pending_payment') {
            return redirect()->route("{$adsRouteNamePrefix}.edit", $ad)->with('info', 'Checkout is not available for this ad.');
        }

        if (! $this->stripeIsConfigured()) {
            return redirect()->route("{$adsRouteNamePrefix}.pay", $ad)->with('error', 'Stripe is not configured for ad payments.');
        }

        $amountCents = max(0, (int) $ad->price_cents);
        $currency = strtolower($this->defaultCurrency());

        if ($currency === 'usd' && $amountCents < 50) {
            return redirect()->route("{$adsRouteNamePrefix}.pay", $ad)->with('error', 'Ad price is below Stripe minimum for card payments.');
        }

        try {
            Stripe::setApiKey((string) config('services.stripe.secret'));
            $session = StripeCheckoutSession::create([
                'mode' => 'payment',
                'customer_email' => auth()->user()->email,
                'client_reference_id' => (string) auth()->id(),
                'success_url' => route("{$adsRouteNamePrefix}.purchase.return", [], true).'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route("{$adsRouteNamePrefix}.purchase.cancel", $ad, true),
                'metadata' => [
                    'app' => 'advertiser_ad',
                    'ad_id' => (string) $ad->id,
                    'user_id' => (string) auth()->id(),
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
            return redirect()
                ->route("{$adsRouteNamePrefix}.pay", $ad)
                ->with('error', config('app.debug')
                    ? 'Payment could not start: '.$e->getMessage()
                    : 'Payment could not start. Please try again.');
        }

        AdPayment::query()->create([
            'ad_id' => $ad->id,
            'user_id' => auth()->id(),
            'amount_cents' => $amountCents,
            'currency' => strtoupper($currency),
            'status' => 'pending',
            'stripe_checkout_session_id' => $session->id,
            'meta' => ['source' => 'checkout_start'],
        ]);

        $ad->update([
            'last_paid_checkout_started_at' => now(),
            'last_paid_checkout_session_id' => $session->id,
        ]);

        $checkoutUrl = $session->url;
        if (! is_string($checkoutUrl) || trim($checkoutUrl) === '') {
            return redirect()->route("{$adsRouteNamePrefix}.pay", $ad)->with('error', 'Could not start checkout.');
        }

        return Inertia::location($checkoutUrl);
    }

    public function purchaseReturn(Request $request, FulfillAdvertiserStripeCheckout $fulfill): RedirectResponse
    {
        $adsIndexRoute = $this->adsRouteName($request, 'index');
        $sessionId = $request->query('session_id');
        if (! is_string($sessionId) || $sessionId === '') {
            return redirect()->route($adsIndexRoute)->with('error', 'Missing payment confirmation.');
        }

        if (! $this->stripeIsConfigured()) {
            return redirect()->route($adsIndexRoute)->with('error', 'Payments are not configured.');
        }

        try {
            Stripe::setApiKey((string) config('services.stripe.secret'));
            $session = StripeCheckoutSession::retrieve($sessionId);
        } catch (\Throwable $e) {
            return redirect()
                ->route($adsIndexRoute)
                ->with('error', config('app.debug')
                    ? 'Could not verify payment: '.$e->getMessage()
                    : 'Could not verify payment. Please contact support.');
        }

        $metadataUserId = (int) ($session->metadata['user_id'] ?? 0);
        if ($metadataUserId !== (int) auth()->id()) {
            abort(403);
        }

        $fulfill($session);

        $adId = (int) ($session->metadata['ad_id'] ?? 0);
        $ad = Ad::query()->whereKey($adId)->where('user_id', auth()->id())->first();

        if ($ad) {
            $ad->refresh();
            $message = $ad->status === 'pending_approval'
                ? 'Payment successful. Your ad is pending administrator approval before it goes live.'
                : 'Payment successful. Your ad is now published.';

            return redirect()->route($adsIndexRoute)->with('success', $message);
        }

        return redirect()->route($adsIndexRoute)->with('success', 'Payment successful.');
    }

    public function purchaseCancel(Request $request, Ad $ad): RedirectResponse
    {
        $this->authorizeAd($ad);

        return redirect()->route($this->adsRouteName($request, 'pay'), $ad)->with('info', 'Checkout was cancelled.');
    }

    private function validateAd(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:140'],
            'description' => ['required', 'string', 'max:5000'],
            'cta_url' => ['required', 'url:http,https', 'max:2048'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'image_file' => ['nullable', 'image', 'max:5120'],
            'clear_image' => ['sometimes', 'boolean'],
        ]);

        $imageUrl = trim((string) ($validated['image_url'] ?? ''));
        if ($imageUrl !== '' && ! preg_match('#^(https?://|/storage/)#i', $imageUrl)) {
            throw ValidationException::withMessages([
                'image_url' => 'Image URL must start with http://, https://, or /storage/.',
            ]);
        }

        return $validated;
    }

    private function resolveImageUrl(Request $request, array &$validated, ?string $fallback = null): ?string
    {
        $clearImage = (bool) ($validated['clear_image'] ?? false);
        $existing = trim((string) ($validated['image_url'] ?? ''));
        unset($validated['image_file'], $validated['image_url'], $validated['clear_image']);

        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('ads', 'public');

            return Storage::url($path);
        }

        if ($clearImage) {
            return null;
        }

        if ($existing !== '') {
            return $existing;
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
        ];
    }

    private function authorizeAd(Ad $ad): void
    {
        abort_unless($ad->user_id === auth()->id(), 404);
    }

    private function defaultPriceCents(): int
    {
        return max(0, (int) config('ads.default_price_cents', 2500));
    }

    private function defaultCurrency(): string
    {
        return strtoupper((string) config('ads.currency', 'USD'));
    }

    private function adPricingPayload(): array
    {
        return [
            'amount_cents' => $this->defaultPriceCents(),
            'currency' => $this->defaultCurrency(),
        ];
    }

    private function stripeIsConfigured(): bool
    {
        return StripeConfig::checkoutConfigured();
    }

    private function adsRouteNamePrefix(Request $request): string
    {
        $routeName = (string) $request->route()?->getName();
        if (Str::startsWith($routeName, 'provider.ads.')) {
            return 'provider.ads';
        }

        if (Str::startsWith($routeName, 'user.ads.')) {
            return 'user.ads';
        }

        return 'advertiser.ads';
    }

    private function adsRouteName(Request $request, string $action): string
    {
        return $this->adsRouteNamePrefix($request).'.'.$action;
    }

    private function adPortalPayload(Request $request): array
    {
        $adsRouteNamePrefix = $this->adsRouteNamePrefix($request);
        $dashboardRouteName = match ($adsRouteNamePrefix) {
            'user.ads' => 'dashboard',
            'provider.ads' => 'provider.dashboard',
            default => 'advertiser.dashboard',
        };

        $analyticsRouteName = match ($adsRouteNamePrefix) {
            'user.ads' => 'user.ads.analytics',
            'provider.ads' => 'provider.ads.analytics',
            default => 'advertiser.analytics',
        };

        return [
            'portal' => match ($adsRouteNamePrefix) {
                'user.ads' => 'user',
                'provider.ads' => 'provider',
                default => 'advertiser',
            },
            'dashboardHref' => route($dashboardRouteName),
            'adsHref' => route($adsRouteNamePrefix.'.index'),
            'analyticsHref' => route($analyticsRouteName),
        ];
    }
}
