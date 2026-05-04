<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\VideoEmbed;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MobileVideoStreamController extends Controller
{
    public function __invoke(VideoEmbed $video): BinaryFileResponse
    {
        abort_unless($video->is_active && $video->isUpload() && $video->file_path, 404);

        if ((float) ($video->price ?? 0) > 0) {
            $hasPurchased = $video->userAccess()
                ->where('user_id', auth()->id())
                ->whereNotNull('purchased_at')
                ->exists();
            abort_unless($hasPurchased, 403);
        }

        abort_unless(Storage::disk(VideoEmbed::VIDEO_UPLOAD_DISK)->exists($video->file_path), 404);

        return Storage::disk(VideoEmbed::VIDEO_UPLOAD_DISK)->response(
            $video->file_path,
            $video->file_name ?: 'video.mp4',
            ['Content-Type' => $video->file_mime ?: 'video/mp4']
        );
    }
}
