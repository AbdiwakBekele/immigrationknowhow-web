<?php

namespace App\Http\Controllers;

use App\Actions\Library\ConfirmEbookShareFromVisit;
use App\Models\EbookShareEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EbookShareLandingController extends Controller
{
    public function __invoke(string $token, Request $request, ConfirmEbookShareFromVisit $confirm): View|RedirectResponse
    {
        $event = EbookShareEvent::query()
            ->where('share_token', $token)
            ->with(['libraryItem.category', 'libraryItem.libraryAuthor'])
            ->firstOrFail();

        $item = $event->libraryItem;
        abort_unless($item && $item->is_active, 404);

        $confirm($event, $request);

        $destination = route('library.show', $item);
        $userAgent = (string) $request->userAgent();
        $isCrawler = preg_match('/facebookexternalhit|Facebot|Twitterbot|LinkedInBot|WhatsApp|Slackbot|TelegramBot|Discordbot/i', $userAgent) === 1;

        if ($isCrawler) {
            return view('ebook-share.landing', [
                'item' => $item,
                'shareUrl' => $event->shareUrl(),
                'coverImageUrl' => $event->coverImageUrl(),
                'destination' => $destination,
                'description' => $item->description
                    ? mb_strimwidth(strip_tags((string) $item->description), 0, 200, '…')
                    : 'Discover immigration ebooks on Immigration Know How.',
            ]);
        }

        return redirect($destination);
    }
}
