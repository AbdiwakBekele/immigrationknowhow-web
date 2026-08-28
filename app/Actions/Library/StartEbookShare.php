<?php

namespace App\Actions\Library;

use App\Models\EbookShareCampaign;
use App\Models\EbookShareEvent;
use App\Models\LibraryItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class StartEbookShare
{
    public function __construct(
        private readonly GetOrCreateEbookShareCampaign $getOrCreateCampaign,
    ) {}

    /**
     * @return array{event: EbookShareEvent, share_url: string, book_url: string}
     */
    public function __invoke(User $user, LibraryItem $item): array
    {
        $this->assertShareable($item);

        return DB::transaction(function () use ($user, $item) {
            $campaign = ($this->getOrCreateCampaign)($user);

            $existing = EbookShareEvent::query()
                ->where('ebook_share_campaign_id', $campaign->id)
                ->where('library_item_id', $item->id)
                ->first();

            if ($existing) {
                return [
                    'event' => $existing,
                    'share_url' => $existing->shareUrl(),
                    'book_url' => route('library.show', $item),
                ];
            }

            do {
                $token = Str::lower(Str::random(32));
            } while (EbookShareEvent::query()->where('share_token', $token)->exists());

            $event = EbookShareEvent::query()->create([
                'ebook_share_campaign_id' => $campaign->id,
                'user_id' => $user->id,
                'library_item_id' => $item->id,
                'share_token' => $token,
                'status' => EbookShareEvent::STATUS_PENDING,
            ]);

            return [
                'event' => $event,
                'share_url' => $event->shareUrl(),
                'book_url' => route('library.show', $item),
            ];
        });
    }

    private function assertShareable(LibraryItem $item): void
    {
        if (! $item->is_active) {
            throw new RuntimeException('This title is not available.');
        }

        $shareableTypes = config('library.share_campaign.shareable_types', ['ebook']);
        if (! in_array($item->type, $shareableTypes, true)) {
            throw new RuntimeException('Only ebooks can be shared for this campaign.');
        }
    }
}
