<?php

namespace App\Actions\Video;

use App\Models\VideoEmbed;
use App\Models\VideoUserAccess;
use Illuminate\Support\Facades\DB;
use Stripe\Checkout\Session;

final class FulfillVideoStripeCheckout
{
    /**
     * Record video purchase after Stripe reports payment succeeded.
     */
    public function __invoke(Session $session): bool
    {
        if (($session->metadata['app'] ?? '') !== 'video') {
            return false;
        }

        if ($session->payment_status !== 'paid') {
            return false;
        }

        $videoId = (int) ($session->metadata['video_id'] ?? 0);
        $userId = (int) ($session->metadata['user_id'] ?? 0);

        if ($videoId < 1 || $userId < 1) {
            return false;
        }

        $video = VideoEmbed::query()
            ->whereKey($videoId)
            ->where('is_active', true)
            ->first();

        if (! $video) {
            return false;
        }

        $price = $video->price !== null ? (float) $video->price : 0.0;
        if ($price <= 0) {
            return false;
        }

        return DB::transaction(function () use ($video, $userId, $session) {
            /** @var VideoUserAccess $access */
            $access = VideoUserAccess::query()->firstOrCreate(
                [
                    'user_id' => $userId,
                    'video_embed_id' => $video->id,
                ],
                []
            );

            $access->refresh();
            if ($access->purchased_at !== null) {
                return true;
            }

            $amountTotal = $session->amount_total;
            $amount = $amountTotal !== null
                ? round(((int) $amountTotal) / 100, 2)
                : $price;

            $currency = strtoupper((string) ($session->currency ?? $video->currency ?? 'USD'));

            $paymentIntentId = $session->payment_intent;
            if (is_object($paymentIntentId) && isset($paymentIntentId->id)) {
                $paymentIntentId = $paymentIntentId->id;
            }

            $access->update([
                'purchased_at' => now(),
                'purchase_amount' => $amount,
                'purchase_currency' => $currency,
                'stripe_checkout_session_id' => $session->id,
                'stripe_payment_intent_id' => is_string($paymentIntentId) ? $paymentIntentId : null,
            ]);

            return true;
        });
    }
}

