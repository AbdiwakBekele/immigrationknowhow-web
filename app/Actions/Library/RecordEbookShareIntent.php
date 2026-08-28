<?php

namespace App\Actions\Library;

use App\Models\EbookShareEvent;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

final class RecordEbookShareIntent
{
    public function __construct(
        private readonly CompleteEbookShareCampaign $completeCampaign,
    ) {}

    public function __invoke(EbookShareEvent $event, string $platform, ?string $ipAddress = null): EbookShareEvent
    {
        $platform = strtolower(trim($platform));
        if (! in_array($platform, ['facebook', 'x', 'other'], true)) {
            throw new RuntimeException('Unsupported share platform.');
        }

        if ($event->isConfirmed()) {
            return $event;
        }

        $rateKey = 'ebook-share-intent:'.$event->user_id;
        $maxAttempts = (int) config('library.share_campaign.intent_rate_limit_per_hour', 10);
        if (RateLimiter::tooManyAttempts($rateKey, $maxAttempts)) {
            throw new RuntimeException('Too many share attempts. Please try again later.');
        }
        RateLimiter::hit($rateKey, 3600);

        $event->fill([
            'platform' => $platform,
            'intent_at' => $event->intent_at ?? now(),
        ]);

        if (config('library.share_campaign.auto_confirm_on_intent', true)) {
            $event->fill([
                'status' => EbookShareEvent::STATUS_CONFIRMED,
                'confirm_source' => EbookShareEvent::CONFIRM_INTENT,
                'confirmed_at' => now(),
            ]);
        }

        $event->save();

        if ($event->isConfirmed()) {
            ($this->completeCampaign)($event->campaign()->firstOrFail());
        }

        return $event->fresh(['libraryItem', 'campaign']);
    }
}
