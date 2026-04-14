<?php

namespace App\Http\Controllers;

use App\Models\VideoEmbed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
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

        return Inertia::render('Videos/Show', [
            'video' => $video,
            'streamUrl' => $video->isUpload() ? route('videos.stream', $video) : null,
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
        abort_unless(Storage::disk(VideoEmbed::VIDEO_UPLOAD_DISK)->exists($video->file_path), 404);

        return Storage::disk(VideoEmbed::VIDEO_UPLOAD_DISK)->response(
            $video->file_path,
            $video->file_name ?: 'video.mp4',
            ['Content-Type' => $video->file_mime ?: 'video/mp4']
        );
    }

    public function download(VideoEmbed $video): BinaryFileResponse|RedirectResponse
    {
        return redirect()
            ->route('videos.show', $video)
            ->with('error', 'Video downloads are not free. Complete purchase before downloading.');
    }

    public function toggleFavorite(VideoEmbed $video): RedirectResponse
    {
        return back()->with('info', 'Favorites are not enabled for videos yet.');
    }

    public function pay(VideoEmbed $video): RedirectResponse
    {
        return redirect()->route('videos.show', $video);
    }

    public function purchaseCancel(VideoEmbed $video): RedirectResponse
    {
        return redirect()
            ->route('videos.show', $video)
            ->with('info', 'Checkout was cancelled.');
    }

    public function purchase(VideoEmbed $video): RedirectResponse
    {
        return redirect()
            ->route('videos.show', $video)
            ->with('info', 'Direct purchases are not enabled for this video.');
    }
}
