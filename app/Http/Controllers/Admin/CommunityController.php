<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommunityPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CommunityController extends Controller
{
    private const CATEGORIES = [
        'feed',
        'ask-intro',
        'ask-announcement',
        'immigration-legal',
        'career-finance',
        'health-wellness',
        'daily-living',
        'culture-community',
    ];

    public function index(): Response
    {
        return Inertia::render('Admin/Community/Index');
    }

    public function list(Request $request): JsonResponse
    {
        Log::info('admin.community.list.request', [
            'query' => $request->query(),
            'user_id' => $request->user()?->id,
            'ip' => $request->ip(),
        ]);

        try {
            $perPage = min(max($request->integer('per_page', 50), 1), 100);
            $page = max($request->integer('page', 1), 1);

            $query = CommunityPost::query()->latest();

            if (! $request->boolean('includeDrafts')) {
                $query->published();
            }

            if ($request->filled('status')) {
                match ($request->string('status')->toString()) {
                    'published' => $query->where('is_published', true),
                    'draft' => $query->where('is_published', false),
                    default => null,
                };
            }

            if ($request->filled('search')) {
                $term = '%'.$request->string('search').'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('title', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhere('tag', 'like', $term);
                });
            }

            $category = $request->filled('category') ? $request->string('category')->toString() : '';
            if ($category !== '' && $category !== 'all' && $category !== 'feed') {
                $query->where('category', $category);
            }

            $posts = $query
                ->paginate($perPage, ['*'], 'page', $page)
                ->withQueryString()
                ->through(fn (CommunityPost $post) => $this->toPostResource($post));

            Log::info('admin.community.list.response', [
                'total' => $posts->total(),
                'count' => count($posts->items()),
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
            ]);

            return response()->json([
                'posts' => $posts,
                'stats' => [
                    'total' => CommunityPost::query()->count(),
                    'published' => CommunityPost::query()->where('is_published', true)->count(),
                    'drafts' => CommunityPost::query()->where('is_published', false)->count(),
                ],
            ]);
        } catch (\Throwable $exception) {
            Log::error('admin.community.list.failed', [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json([
                'posts' => ['data' => []],
                'stats' => ['total' => 0, 'published' => 0, 'drafts' => 0],
                'error' => 'Failed to load admin community posts.',
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'tag' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', Rule::in(self::CATEGORIES)],
            'image' => ['nullable', 'file', 'image', 'max:8192'],
            'video' => ['nullable', 'file', 'mimetypes:video/mp4,video/webm,video/ogg,video/quicktime', 'max:102400'],
            'video_url' => ['nullable', 'url', 'max:2048'],
            'is_published' => ['boolean'],
        ]);

        $isPublished = (bool) ($validated['is_published'] ?? true);

        $post = CommunityPost::query()->create([
            'author_id' => $request->user()?->id,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'tag' => $validated['tag'] ?? '',
            'category' => $validated['category'] ?? 'feed',
            'is_published' => $isPublished,
            'published_at' => $isPublished ? now() : null,
            'image_url' => $request->hasFile('image')
                ? Storage::url($request->file('image')->store('community/images', 'public'))
                : null,
            'video_url' => $request->hasFile('video')
                ? Storage::url($request->file('video')->store('community/videos', 'public'))
                : ($validated['video_url'] ?? null),
        ]);

        return response()->json(['post' => $this->toPostResource($post)], 201);
    }

    public function update(Request $request, CommunityPost $communityPost): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'tag' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', Rule::in(self::CATEGORIES)],
            'image' => ['nullable', 'file', 'image', 'max:8192'],
            'video' => ['nullable', 'file', 'mimetypes:video/mp4,video/webm,video/ogg,video/quicktime', 'max:102400'],
            'video_url' => ['nullable', 'url', 'max:2048'],
            'is_published' => ['required', 'boolean'],
        ]);

        $imageUrl = $communityPost->image_url;
        if ($request->hasFile('image')) {
            $imageUrl = Storage::url($request->file('image')->store('community/images', 'public'));
        }

        $videoUrl = $communityPost->video_url;
        if ($request->hasFile('video')) {
            $videoUrl = Storage::url($request->file('video')->store('community/videos', 'public'));
        } elseif (array_key_exists('video_url', $validated)) {
            $videoUrl = $validated['video_url'] ?: null;
        }

        $communityPost->update([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'tag' => $validated['tag'] ?? '',
            'category' => $validated['category'] ?? 'feed',
            'is_published' => $validated['is_published'],
            'published_at' => $validated['is_published']
                ? ($communityPost->published_at ?? now())
                : null,
            'image_url' => $imageUrl,
            'video_url' => $videoUrl,
        ]);

        return response()->json(['post' => $this->toPostResource($communityPost->fresh())]);
    }

    public function destroy(CommunityPost $communityPost): JsonResponse
    {
        $communityPost->delete();

        return response()->json(['success' => true]);
    }

    private function toPostResource(CommunityPost $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'description' => $post->description,
            'tag' => $post->tag,
            'category' => $post->category,
            'image_url' => $post->image_url,
            'video_url' => $post->video_url,
            'likes_count' => (int) $post->likes_count,
            'comments_count' => (int) $post->comments_count,
            'shares_count' => (int) $post->shares_count,
            'bookmarks_count' => (int) $post->bookmarks_count,
            'is_published' => (bool) $post->is_published,
            'published_at' => optional($post->published_at)->toIso8601String(),
            'created_at' => optional($post->created_at)->toIso8601String(),
        ];
    }
}
