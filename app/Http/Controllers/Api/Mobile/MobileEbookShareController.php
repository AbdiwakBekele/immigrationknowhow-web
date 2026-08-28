<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Actions\Library\RecordEbookShareIntent;
use App\Actions\Library\StartEbookShare;
use App\Http\Controllers\Controller;
use App\Models\LibraryItem;
use App\Support\EbookShareCampaignPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class MobileEbookShareController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => EbookShareCampaignPresenter::forUser($request->user()),
        ]);
    }

    public function start(Request $request, LibraryItem $item, StartEbookShare $start): JsonResponse
    {
        abort_unless($item->is_active, 404);

        try {
            $result = $start($request->user(), $item);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => (object) [],
            ], 422);
        }

        $event = $result['event'];

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'share_url' => $result['share_url'],
                'book_url' => $result['book_url'],
                'event' => array_merge([
                    'id' => $event->id,
                    'status' => $event->status,
                    'library_item_id' => $event->library_item_id,
                ], $event->sharePreview()),
            ],
        ]);
    }

    public function intent(Request $request, LibraryItem $item, StartEbookShare $start, RecordEbookShareIntent $recordIntent): JsonResponse
    {
        abort_unless($item->is_active, 404);

        $validated = $request->validate([
            'platform' => ['required', 'string', 'in:facebook,x,other'],
        ]);

        try {
            $result = $start($request->user(), $item);
            $event = $recordIntent($result['event'], $validated['platform'], $request->ip());
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => (object) [],
            ], 422);
        }

        $campaign = EbookShareCampaignPresenter::forUser($request->user());

        return response()->json([
            'success' => true,
            'message' => $event->isConfirmed()
                ? 'Share recorded.'
                : 'Share started. It will be confirmed when someone opens your link from social media.',
            'data' => [
                'share_url' => $event->shareUrl(),
                'cover_image_url' => $event->coverImageUrl(),
                'title' => $item->title,
                'event' => array_merge([
                    'id' => $event->id,
                    'status' => $event->status,
                    'platform' => $event->platform,
                    'confirmed_at' => $event->confirmed_at?->toIso8601String(),
                ], $event->sharePreview()),
                'campaign' => $campaign,
            ],
        ]);
    }
}
