<?php

namespace App\Actions\Library;

use App\Models\EbookShareEvent;
use App\Support\SocialReferrer;
use Illuminate\Http\Request;
use RuntimeException;

final class ConfirmEbookShareFromVisit
{
    public function __construct(
        private readonly CompleteEbookShareCampaign $completeCampaign,
    ) {}

    public function __invoke(EbookShareEvent $event, Request $request): EbookShareEvent
    {
        if ($event->isConfirmed()) {
            return $event;
        }

        if ($event->intent_at === null) {
            return $event;
        }

        $windowHours = (int) config('library.share_campaign.confirmation_window_hours', 48);
        if ($event->intent_at->lt(now()->subHours($windowHours))) {
            return $event;
        }

        $referrer = (string) $request->headers->get('referer', '');
        $requireSocial = (bool) config('library.share_campaign.require_social_referrer', false);
        if ($requireSocial && ! SocialReferrer::isSocial($referrer)) {
            return $event;
        }

        $visitorUserId = $request->user()?->id;
        if ($visitorUserId !== null && (int) $visitorUserId === (int) $event->user_id) {
            return $event;
        }

        $visitorIp = (string) $request->ip();
        if ($visitorIp !== '' && $event->click_ip === $visitorIp) {
            return $event;
        }

        $event->update([
            'status' => EbookShareEvent::STATUS_CONFIRMED,
            'confirm_source' => EbookShareEvent::CONFIRM_REFERRER,
            'confirmed_at' => now(),
            'referrer' => $referrer !== '' ? mb_substr($referrer, 0, 512) : null,
            'click_ip' => $visitorIp !== '' ? $visitorIp : null,
        ]);

        ($this->completeCampaign)($event->campaign()->firstOrFail());

        return $event->fresh(['libraryItem', 'campaign']);
    }
}
