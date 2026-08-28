<?php

namespace App\Http\Controllers;

use App\Models\EbookShareEvent;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EbookShareCoverController extends Controller
{
    public function __invoke(string $token): Response|StreamedResponse
    {
        $event = EbookShareEvent::query()
            ->where('share_token', $token)
            ->with('libraryItem')
            ->firstOrFail();

        $item = $event->libraryItem;
        abort_unless($item && $item->is_active && $item->cover_image, 404);

        $path = (string) $item->cover_image;
        if (str_starts_with($path, 'http')) {
            return redirect()->away($path);
        }

        $disk = (string) config('uploads.library_covers.disk', 's3');

        abort_unless(Storage::disk($disk)->exists($path), 404);

        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/png',
        };

        return Storage::disk($disk)->response($path, null, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
