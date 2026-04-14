<?php

namespace App\Http\Controllers;

use App\Actions\Video\FulfillVideoStripeCheckout;
use App\Models\VideoEmbed;
use App\Models\VideoUserAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Stripe;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Public/user video browsing controller.
 */
class VideoProductController extends Controller
{
    public function index(Request $request): Response
    {
        $query = VideoEmbed::query()->active()->ordered();

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($builder) use ($search) {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if ($request->filled('platform')) {
            $query->where('platform', $request->string('platform')->toString());
        }

        if ($request->filled('category')) {
            $query->where('category', $request->string('category')->toString());
        }

        $videos = $query->paginate(16)->withQueryString();

        return Inertia::render('Videos/Index', [
            'videos' => $videos,
            'filters' => $request->only(['search', 'platform', 'category']),
            'platformOptions' => VideoEmbed::query()
                ->active()
                ->select('platform')
                ->distinct()
                ->orderBy('platform')
                ->pluck('platform')
                ->values(),
            'categoryOptions' => VideoEmbed::query()
                ->active()
                ->whereNotNull('category')
                ->where('category', '!=', '')
                ->select('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category')
                ->values(),
        ]);
    }

    public function show(VideoEmbed $video): Response
    {
        abort_unless($video->is_active, 404);
        $video->incrementViews();

        $requiresPaidAccess = (float) ($video->price ?? 0) > 0;
        $userAccess = auth()->id()
            ? $video->userAccess()->where('user_id', auth()->id())->first()
            : null;
        $hasAccess = ! $requiresPaidAccess || (bool) $userAccess?->purchased_at;
        $stripeConfigured = $this->stripeIsConfigured();
        $stripeSetupNote = $stripeConfigured
            ? null
            : (config('app.debug')
                ? 'Add STRIPE_KEY and STRIPE_SECRET to enable online checkout. Run php artisan config:clear after editing .env.'
                : 'Online checkout is not available right now.');

        return Inertia::render('Videos/Show', [
            'video' => $video,
            'streamUrl' => $hasAccess && $video->isUpload() ? route('videos.stream', $video) : null,
            'hasAccess' => $hasAccess,
            'requiresPaidAccess' => $requiresPaidAccess,
            'stripeSetupNote' => $stripeSetupNote,
            'relatedVideos' => VideoEmbed::query()
                ->active()
                ->where('id', '!=', $video->id)
                ->when($video->category, fn ($q) => $q->where('category', $video->category))
                ->ordered()
                ->limit(8)
                ->get(['id', 'title', 'slug', 'platform', 'thumbnail_url', 'category']),
        ]);
    }

    public function stream(VideoEmbed $video): BinaryFileResponse
    {
        abort_unless($video->is_active && $video->isUpload() && $video->file_path, 404);
        if ((float) ($video->price ?? 0) > 0) {
            $hasPurchased = auth()->id()
                ? $video->userAccess()
                    ->where('user_id', auth()->id())
                    ->whereNotNull('purchased_at')
                    ->exists()
                : false;
            abort_unless($hasPurchased, 403);
        }
        abort_unless(Storage::disk(VideoEmbed::VIDEO_UPLOAD_DISK)->exists($video->file_path), 404);

        return Storage::disk(VideoEmbed::VIDEO_UPLOAD_DISK)->response(
            $video->file_path,
            $video->file_name ?: 'video.mp4',
            ['Content-Type' => $video->file_mime ?: 'video/mp4']
        );
    }

    public function download(VideoEmbed $video): BinaryFileResponse|RedirectResponse
    {
        abort_unless($video->is_active, 404);

        $requiresPaidAccess = (float) ($video->price ?? 0) > 0;
        if ($requiresPaidAccess) {
            $hasPurchased = auth()->id()
                ? $video->userAccess()
                    ->where('user_id', auth()->id())
                    ->whereNotNull('purchased_at')
                    ->exists()
                : false;
            if (! $hasPurchased) {
                return redirect()
                    ->route('videos.show', $video)
                    ->with('error', 'Complete purchase before downloading.');
            }
        }

        if ($video->isUpload() && $video->file_path) {
            if (! Storage::disk(VideoEmbed::VIDEO_UPLOAD_DISK)->exists($video->file_path)) {
                return redirect()->route('videos.show', $video)->with('error', 'Video file not found.');
            }

            return Storage::disk(VideoEmbed::VIDEO_UPLOAD_DISK)->download(
                $video->file_path,
                $video->file_name ?: 'video.mp4'
            );
        }

        if ($video->video_url) {
            return redirect()->away($video->video_url);
        }

        return redirect()->route('videos.show', $video)->with('error', 'No downloadable source available.');
    }

    public function toggleFavorite(VideoEmbed $video): RedirectResponse
    {
        return back()->with('info', 'Favorites are not enabled for videos yet.');
    }

    public function pay(VideoEmbed $video): Response|RedirectResponse
    {
        abort_unless($video->is_active, 404);

        $price = (float) ($video->price ?? 0);
        if ($price <= 0) {
            return redirect()->route('videos.show', $video)->with('info', 'This video is already free.');
        }

        if ($video->userAccess()
            ->where('user_id', auth()->id())
            ->whereNotNull('purchased_at')
            ->exists()) {
            return redirect()->route('videos.show', $video)->with('success', 'You already have access to this video.');
        }

        if (! $this->stripeIsConfigured()) {
            return redirect()->route('videos.show', $video)->with('error', 'Payments are not configured yet.');
        }

        Stripe::setApiKey((string) config('services.stripe.secret'));

        $currency = strtolower((string) ($video->currency ?? 'USD'));
        $unitAmount = (int) round($price * 100);
        if ($currency === 'usd' && $unitAmount < 50) {
            return redirect()->route('videos.show', $video)->with('error', 'Price is below Stripe minimum for card payments.');
        }

        $description = Str::limit(strip_tags((string) $video->description), 450);
        if ($description === '') {
            $description = 'Digital video';
        }

        $session = StripeCheckoutSession::create([
            'ui_mode' => 'embedded_page',
            'mode' => 'payment',
            'customer_email' => auth()->user()->email,
            'client_reference_id' => (string) auth()->id(),
            'return_url' => route('videos.purchase.return', [], true).'?session_id={CHECKOUT_SESSION_ID}',
            'metadata' => [
                'app' => 'video',
                'video_id' => (string) $video->id,
                'user_id' => (string) auth()->id(),
            ],
            'line_items' => [[
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => $unitAmount,
                    'product_data' => [
                        'name' => $video->title,
                        'description' => $description,
                    ],
                ],
                'quantity' => 1,
            ]],
        ]);

        $clientSecret = $session->client_secret;
        if (! is_string($clientSecret) || $clientSecret === '') {
            return redirect()->route('videos.show', $video)->with('error', 'Could not start checkout. Please try again.');
        }

        return Inertia::render('Videos/Payment', [
            'item' => [
                'title' => $video->title,
                'slug' => $video->slug,
                'price' => $video->price,
                'currency' => $video->currency ?? 'USD',
            ],
            'checkoutClientSecret' => $clientSecret,
            'stripePublishableKey' => (string) config('services.stripe.key'),
        ]);
    }

    public function purchaseCancel(VideoEmbed $video): RedirectResponse
    {
        return redirect()
            ->route('videos.show', $video)
            ->with('info', 'Checkout was cancelled.');
    }

    public function purchase(VideoEmbed $video): RedirectResponse
    {
        abort_unless($video->is_active, 404);

        $price = (float) ($video->price ?? 0);
        if ($price > 0) {
            return redirect()
                ->route('videos.pay', $video)
                ->with('info', 'Use card checkout to unlock this video.');
        }

        $access = VideoUserAccess::query()->firstOrCreate([
            'user_id' => auth()->id(),
            'video_embed_id' => $video->id,
        ]);

        if (! $access->purchased_at) {
            $access->update([
                'purchased_at' => now(),
                'purchase_amount' => 0,
                'purchase_currency' => $video->currency ?? 'USD',
            ]);
        }

        return redirect()
            ->route('videos.download', $video)
            ->with('success', 'Added to your library. Your download will start shortly.');
    }

    public function purchaseReturn(Request $request, FulfillVideoStripeCheckout $fulfill): RedirectResponse
    {
        $sessionId = $request->query('session_id');
        if (! is_string($sessionId) || $sessionId === '') {
            return redirect()->route('videos.index')->with('error', 'Missing payment confirmation.');
        }

        $secret = config('services.stripe.secret');
        if (! is_string($secret) || $secret === '') {
            return redirect()->route('videos.index')->with('error', 'Payments are not configured.');
        }

        Stripe::setApiKey($secret);
        $session = StripeCheckoutSession::retrieve($sessionId);

        $metadataUserId = (int) ($session->metadata['user_id'] ?? 0);
        if ($metadataUserId !== (int) auth()->id()) {
            abort(403);
        }

        $fulfill($session);

        $videoId = (int) ($session->metadata['video_id'] ?? 0);
        $video = VideoEmbed::query()->whereKey($videoId)->first();

        if ($video) {
            return redirect()->route('videos.show', $video)->with('success', 'Payment successful. You can watch and download your video.');
        }

        return redirect()->route('videos.index')->with('success', 'Payment successful.');
    }

    private function stripeIsConfigured(): bool
    {
        $secret = config('services.stripe.secret');
        $publishable = config('services.stripe.key');

        return is_string($secret) && $secret !== ''
            && is_string($publishable) && $publishable !== '';
    }
}
