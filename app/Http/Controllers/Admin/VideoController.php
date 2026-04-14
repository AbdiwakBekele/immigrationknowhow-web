<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VideoEmbed;
use App\Support\UploadLimit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VideoController extends Controller
{
    private const PLATFORMS = ['youtube', 'vimeo', 'tiktok', 'upload', 'other'];

    public function index(Request $request): Response
    {
        $query = VideoEmbed::query();

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($builder) use ($search) {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('platform', 'like', "%{$search}%");
            });
        }

        if ($request->filled('platform')) {
            $query->where('platform', $request->string('platform')->toString());
        }

        $videos = $query
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Admin/Videos/Index', [
            'videos' => $videos,
            'filters' => $request->only(['search', 'platform']),
            'platformOptions' => collect(self::PLATFORMS)->map(fn (string $p) => [
                'value' => $p,
                'label' => $p === 'upload' ? 'Uploaded file' : ucfirst($p),
            ])->values()->all(),
            'stats' => [
                'total' => VideoEmbed::count(),
                'featured' => VideoEmbed::where('is_featured', true)->count(),
                'active' => VideoEmbed::where('is_active', true)->count(),
            ],
            'maxUploadMb' => UploadLimit::videoMaxMb(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Videos/Create', [
            'maxUploadMb' => UploadLimit::videoMaxMb(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $maxKb = UploadLimit::videoMaxKb();

        $source = $request->string('source')->toString() ?: VideoEmbed::SOURCE_EMBED;

        if ($source === VideoEmbed::SOURCE_UPLOAD) {
            $validated = $request->validate([
                'title' => ['required', 'string', 'max:255'],
                'video_file' => [
                    'required',
                    'file',
                    'mimes:mp4,webm,mov',
                    'max:'.$maxKb,
                ],
                'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
                'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
                'description' => ['nullable', 'string', 'max:10000'],
                'category' => ['nullable', 'string', 'max:255'],
                'tags' => ['nullable', 'string', 'max:2000'],
                'is_featured' => ['boolean'],
                'is_active' => ['boolean'],
                'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            ], [
                'video_file.uploaded' => UploadLimit::uploadFailedMessage(),
                'price.required' => 'Price is required. Use 0 for free content.',
                'currency.required' => 'Currency is required (3 letters, e.g. USD).',
            ]);

            $tags = $this->parseTags($validated['tags'] ?? null);
            $file = $request->file('video_file');
            $uuid = (string) Str::uuid();
            $ext = strtolower($file->getClientOriginalExtension() ?: 'mp4');
            if (! in_array($ext, ['mp4', 'webm', 'mov'], true)) {
                $ext = 'mp4';
            }
            $path = $file->storeAs(
                'videos/'.$uuid,
                'video.'.$ext,
                VideoEmbed::VIDEO_UPLOAD_DISK
            );

            $currency = strtoupper((string) $validated['currency']);

            $video = new VideoEmbed([
                'uuid' => $uuid,
                'source' => VideoEmbed::SOURCE_UPLOAD,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'category' => $validated['category'] ?? null,
                'tags' => $tags,
                'platform' => 'upload',
                'video_url' => '',
                'video_id' => '-',
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_mime' => $file->getMimeType() ?: 'video/mp4',
                'file_size' => $file->getSize(),
                'price' => $validated['price'],
                'currency' => $currency,
                'is_featured' => $validated['is_featured'] ?? false,
                'is_active' => $validated['is_active'] ?? true,
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);
            $video->created_by = $request->user()->id;
            $video->save();
        } else {
            $validated = $request->validate([
                'title' => ['required', 'string', 'max:255'],
                'video_url' => ['required', 'url', 'max:2048'],
                'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
                'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
                'description' => ['nullable', 'string', 'max:10000'],
                'category' => ['nullable', 'string', 'max:255'],
                'tags' => ['nullable', 'string', 'max:2000'],
                'is_featured' => ['boolean'],
                'is_active' => ['boolean'],
                'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            ], [
                'price.required' => 'Price is required. Use 0 for free content.',
                'currency.required' => 'Currency is required (3 letters, e.g. USD).',
            ]);

            $tags = $this->parseTags($validated['tags'] ?? null);

            $video = new VideoEmbed(array_merge(
                $this->payloadFromValidated($validated, $tags),
                ['source' => VideoEmbed::SOURCE_EMBED]
            ));
            $video->created_by = $request->user()->id;
            $video->save();
        }

        return redirect()
            ->route('admin.videos.index')
            ->with('success', 'Video added.');
    }

    public function edit(VideoEmbed $video): Response
    {
        return Inertia::render('Admin/Videos/Edit', [
            'video' => [
                'slug' => $video->slug,
                'source' => $video->source ?? VideoEmbed::SOURCE_EMBED,
                'title' => $video->title,
                'video_url' => $video->video_url,
                'description' => $video->description,
                'category' => $video->category,
                'tags' => is_array($video->tags) && count($video->tags) ? implode(', ', $video->tags) : '',
                'is_featured' => $video->is_featured,
                'is_active' => $video->is_active,
                'sort_order' => $video->sort_order,
                'platform' => $video->platform,
                'file_name' => $video->file_name,
                'price' => $video->price,
                'currency' => $video->currency ?? 'USD',
            ],
            'maxUploadMb' => UploadLimit::videoMaxMb(),
        ]);
    }

    public function update(Request $request, VideoEmbed $video): RedirectResponse
    {
        $maxKb = UploadLimit::videoMaxKb();

        if (($video->source ?? VideoEmbed::SOURCE_EMBED) === VideoEmbed::SOURCE_UPLOAD) {
            $validated = $request->validate([
                'title' => ['required', 'string', 'max:255'],
                'video_file' => [
                    'nullable',
                    'file',
                    'mimes:mp4,webm,mov',
                    'max:'.$maxKb,
                ],
                'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
                'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
                'description' => ['nullable', 'string', 'max:10000'],
                'category' => ['nullable', 'string', 'max:255'],
                'tags' => ['nullable', 'string', 'max:2000'],
                'is_featured' => ['boolean'],
                'is_active' => ['boolean'],
                'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            ], [
                'video_file.uploaded' => UploadLimit::uploadFailedMessage(),
                'price.required' => 'Price is required. Use 0 for free content.',
                'currency.required' => 'Currency is required (3 letters, e.g. USD).',
            ]);

            $tags = $this->parseTags($validated['tags'] ?? null);

            $currency = strtoupper((string) $validated['currency']);

            $video->fill([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'category' => $validated['category'] ?? null,
                'tags' => $tags,
                'price' => $validated['price'],
                'currency' => $currency,
                'is_featured' => $validated['is_featured'] ?? false,
                'is_active' => $validated['is_active'] ?? true,
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);

            if ($request->hasFile('video_file')) {
                if ($video->file_path) {
                    Storage::disk(VideoEmbed::VIDEO_UPLOAD_DISK)->delete($video->file_path);
                }
                $file = $request->file('video_file');
                $ext = strtolower($file->getClientOriginalExtension() ?: 'mp4');
                if (! in_array($ext, ['mp4', 'webm', 'mov'], true)) {
                    $ext = 'mp4';
                }
                $dir = 'videos/'.$video->uuid;
                $path = $file->storeAs($dir, 'video.'.$ext, VideoEmbed::VIDEO_UPLOAD_DISK);
                $video->file_path = $path;
                $video->file_name = $file->getClientOriginalName();
                $video->file_mime = $file->getMimeType() ?: 'video/mp4';
                $video->file_size = $file->getSize();
            }

            $video->save();
        } else {
            $validated = $request->validate([
                'title' => ['required', 'string', 'max:255'],
                'video_url' => ['required', 'url', 'max:2048'],
                'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
                'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
                'description' => ['nullable', 'string', 'max:10000'],
                'category' => ['nullable', 'string', 'max:255'],
                'tags' => ['nullable', 'string', 'max:2000'],
                'is_featured' => ['boolean'],
                'is_active' => ['boolean'],
                'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            ], [
                'price.required' => 'Price is required. Use 0 for free content.',
                'currency.required' => 'Currency is required (3 letters, e.g. USD).',
            ]);

            $tags = $this->parseTags($validated['tags'] ?? null);

            $video->fill($this->payloadFromValidated($validated, $tags));
            $video->save();
        }

        return redirect()
            ->route('admin.videos.index')
            ->with('success', 'Video updated.');
    }

    public function stream(VideoEmbed $video): BinaryFileResponse
    {
        if (($video->source ?? VideoEmbed::SOURCE_EMBED) !== VideoEmbed::SOURCE_UPLOAD || ! $video->file_path) {
            abort(404);
        }

        if (! Storage::disk(VideoEmbed::VIDEO_UPLOAD_DISK)->exists($video->file_path)) {
            abort(404);
        }

        return Storage::disk(VideoEmbed::VIDEO_UPLOAD_DISK)->response(
            $video->file_path,
            $video->file_name ?: 'video.mp4',
            ['Content-Type' => $video->file_mime ?: 'video/mp4']
        );
    }

    public function destroy(VideoEmbed $video): RedirectResponse
    {
        if (($video->source ?? VideoEmbed::SOURCE_EMBED) === VideoEmbed::SOURCE_UPLOAD && $video->file_path) {
            Storage::disk(VideoEmbed::VIDEO_UPLOAD_DISK)->delete($video->file_path);
        }

        $video->delete();

        return redirect()
            ->route('admin.videos.index')
            ->with('success', 'Video removed.');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function payloadFromValidated(array $validated, ?array $tags): array
    {
        $currency = strtoupper((string) $validated['currency']);

        return [
            'title' => $validated['title'],
            'video_url' => $validated['video_url'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'] ?? null,
            'tags' => $tags,
            'price' => $validated['price'],
            'currency' => $currency,
            'is_featured' => $validated['is_featured'] ?? false,
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
        ];
    }

    private function parseTags(?string $raw): ?array
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $parts = array_filter(array_map('trim', explode(',', $raw)));

        return count($parts) ? array_values($parts) : null;
    }
}
