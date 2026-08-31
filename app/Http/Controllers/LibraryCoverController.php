<?php

namespace App\Http\Controllers;

use App\Models\LibraryItem;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LibraryCoverController extends Controller
{
    public function __invoke(LibraryItem $item): Response|StreamedResponse
    {
        abort_unless($item->is_active, 404);
        abort_unless($item->cover_image, 404);

        $path = (string) $item->cover_image;
        if (str_starts_with($path, 'http')) {
            return redirect()->away($path);
        }

        $configuredDisk = (string) config('uploads.library_covers.disk', 's3');

        foreach (array_unique([$configuredDisk, 'public']) as $disk) {
            if (! Storage::disk($disk)->exists($path)) {
                continue;
            }

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

        abort(404);
    }
}
