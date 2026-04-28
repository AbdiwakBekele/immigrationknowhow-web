<?php

namespace App\Http\Controllers;

use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\CommunityPostReaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CommunityController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Community/Index', [
            'dashboardContext' => $this->dashboardContext($request),
        ]);
    }

    public function postPage(Request $request, CommunityPost $communityPost): Response
    {
        abort_unless($communityPost->is_published, 404);

        return Inertia::render('Community/Post', [
            'postId' => $communityPost->id,
            'dashboardContext' => $this->dashboardContext($request),
        ]);
    }

    private function dashboardContext(Request $request): string
    {
        $user = $request->user();
        if (! $user) {
            return 'guest';
        }

        if (method_exists($user, 'hasRole')) {
            if ($user->hasRole('provider')) {
                return 'provider';
            }
            if ($user->hasRole('user')) {
                return 'user';
            }
        }

        return 'guest';
    }

    public function posts(Request $request): JsonResponse
    {
        if (! Schema::hasTable('community_posts')) {
            Log::warning('community.posts.table_missing', [
                'table' => 'community_posts',
                'path' => $request->path(),
                'query' => $request->query(),
            ]);

            return response()->json([
                'posts' => [
                    'current_page' => 1,
                    'data' => [],
                    'first_page_url' => $request->url().'?page=1',
                    'from' => null,
                    'last_page' => 1,
                    'last_page_url' => $request->url().'?page=1',
                    'links' => [],
                    'next_page_url' => null,
                    'path' => $request->url(),
                    'per_page' => 20,
                    'prev_page_url' => null,
                    'to' => null,
                    'total' => 0,
                ],
            ]);
        }

        Log::info('community.posts.request', [
            'path' => $request->path(),
            'query' => $request->query(),
            'user_id' => $request->user()?->id,
            'ip' => $request->ip(),
        ]);

        try {
            $requestedCategory = $request->filled('category') ? trim($request->string('category')->toString()) : 'feed';
            if ($requestedCategory === '') {
                $requestedCategory = 'feed';
            }
            $requestedSearch = $request->filled('search') ? trim($request->string('search')->toString()) : '';
            $publishedBaseCount = CommunityPost::query()->published()->count();
            Log::info('community.posts.diagnostics.start', [
                'published_count' => $publishedBaseCount,
                'requested_category' => $requestedCategory,
                'requested_search' => $requestedSearch,
            ]);

            $query = CommunityPost::query()->published()->latest();

            if ($requestedCategory !== 'feed') {
                $query->where('category', $requestedCategory);
            }

            if ($requestedSearch !== '') {
                $term = '%'.$requestedSearch.'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('title', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhere('tag', 'like', $term);
                });
            }

            $prePaginationCount = (clone $query)->count();
            Log::info('community.posts.diagnostics.query', [
                'requested_category' => $requestedCategory,
                'requested_search' => $requestedSearch,
                'matched_before_pagination' => $prePaginationCount,
            ]);

            $paginated = $query->paginate(20);
            $items = $paginated->getCollection();
            $userId = $request->user()?->id;
            $guestKey = $request->query('guest_key');
            $dedupeKey = null;
            if ($userId) {
                $dedupeKey = 'u'.$userId;
            } elseif (is_string($guestKey) && $guestKey !== '' && Str::isUuid($guestKey)) {
                $dedupeKey = 'g'.Str::lower($guestKey);
            }

            $reactionMap = [];
            if ($dedupeKey && $items->isNotEmpty() && $this->canQueryReactionDedupe()) {
                $reactionMap = CommunityPostReaction::query()
                    ->where('dedupe_key', $dedupeKey)
                    ->whereIn('community_post_id', $items->pluck('id'))
                    ->get()
                    ->groupBy('community_post_id')
                    ->map(fn ($rows) => $rows->pluck('type')->values()->all())
                    ->toArray();
            }

            $posts = $paginated->through(fn (CommunityPost $post) => [
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
                'user_reactions' => $reactionMap[$post->id] ?? [],
                'created_at' => optional($post->created_at)->toIso8601String(),
            ]);

            Log::info('community.posts.response', [
                'total' => $posts->total(),
                'count' => count($posts->items()),
                'current_page' => $posts->currentPage(),
                'post_ids' => collect($posts->items())->pluck('id')->values()->all(),
                'post_categories' => collect($posts->items())->pluck('category')->values()->all(),
                'dedupe_key_present' => $dedupeKey !== null,
            ]);

            if ($publishedBaseCount > 0 && count($posts->items()) === 0) {
                Log::warning('community.posts.empty_while_published_exists', [
                    'published_count' => $publishedBaseCount,
                    'requested_category' => $requestedCategory,
                    'requested_search' => $requestedSearch,
                    'matched_before_pagination' => $prePaginationCount,
                    'current_page' => $posts->currentPage(),
                ]);
            }

            return response()->json(['posts' => $posts]);
        } catch (\Throwable $exception) {
            Log::error('community.posts.failed', [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json([
                'posts' => ['data' => []],
                'error' => 'Failed to load community posts.',
            ], 500);
        }
    }

    public function show(Request $request, CommunityPost $communityPost): JsonResponse
    {
        if (! $communityPost->is_published) {
            return response()->json([
                'error' => 'Community post not found.',
            ], 404);
        }

        $userId = $request->user()?->id;
        $guestKey = $request->query('guest_key');
        $dedupeKey = null;
        if ($userId) {
            $dedupeKey = 'u'.$userId;
        } elseif (is_string($guestKey) && $guestKey !== '' && Str::isUuid($guestKey)) {
            $dedupeKey = 'g'.Str::lower($guestKey);
        }

        $userReactions = [];
        if ($dedupeKey) {
            $userReactions = CommunityPostReaction::query()
                ->where('dedupe_key', $dedupeKey)
                ->where('community_post_id', $communityPost->id)
                ->pluck('type')
                ->values()
                ->all();
        }

        return response()->json([
            'post' => [
                'id' => $communityPost->id,
                'title' => $communityPost->title,
                'description' => $communityPost->description,
                'tag' => $communityPost->tag,
                'category' => $communityPost->category,
                'image_url' => $communityPost->image_url,
                'video_url' => $communityPost->video_url,
                'likes_count' => (int) $communityPost->likes_count,
                'comments_count' => (int) $communityPost->comments_count,
                'shares_count' => (int) $communityPost->shares_count,
                'bookmarks_count' => (int) $communityPost->bookmarks_count,
                'user_reactions' => $userReactions,
                'created_at' => optional($communityPost->created_at)->toIso8601String(),
            ],
        ]);
    }

    public function react(Request $request, CommunityPost $communityPost): JsonResponse
    {
        Log::info('community.react.request', [
            'post_id' => $communityPost->id,
            'is_published' => (bool) $communityPost->is_published,
            'type_raw' => $request->input('type'),
            'user_id' => $request->user()?->id,
            'has_user' => $request->user() !== null,
            'wants_json' => $request->wantsJson(),
        ]);

        try {
            if (! $communityPost->is_published) {
                return response()->json([
                    'error' => 'Community post not found.',
                ], 404);
            }

            $userId = $request->user()?->id;
            $validated = $request->validate(
                array_merge(
                    [
                        'type' => ['required', 'in:like,share,bookmark'],
                    ],
                    $userId ? [] : [
                        'guest_key' => ['required', 'uuid'],
                    ],
                ),
            );

            $dedupeKey = $userId
                ? 'u'.$userId
                : 'g'.Str::lower($request->string('guest_key')->toString());

            $field = $validated['type'] === 'like'
                ? 'likes_count'
                : ($validated['type'] === 'share' ? 'shares_count' : 'bookmarks_count');

            $active = false;
            DB::transaction(function () use ($communityPost, $validated, $userId, $dedupeKey, $field, &$active): void {
                $reaction = CommunityPostReaction::query()
                    ->where('community_post_id', $communityPost->id)
                    ->where('dedupe_key', $dedupeKey)
                    ->where('type', $validated['type'])
                    ->first();

                // Share is one-time per user/post; like and bookmark are toggleable.
                if ($validated['type'] === 'share') {
                    if (! $reaction) {
                        CommunityPostReaction::query()->create([
                            'community_post_id' => $communityPost->id,
                            'user_id' => $userId,
                            'dedupe_key' => $dedupeKey,
                            'type' => 'share',
                        ]);
                        $communityPost->increment($field);
                    }
                    $active = true;

                    return;
                }

                if ($reaction) {
                    $reaction->delete();
                    $communityPost->refresh();
                    $communityPost->{$field} = max(0, (int) $communityPost->{$field} - 1);
                    $communityPost->save();
                    $active = false;

                    return;
                }

                CommunityPostReaction::query()->create([
                    'community_post_id' => $communityPost->id,
                    'user_id' => $userId,
                    'dedupe_key' => $dedupeKey,
                    'type' => $validated['type'],
                ]);
                $communityPost->increment($field);
                $active = true;
            });

            $communityPost->refresh();

            Log::info('community.react.success', [
                'post_id' => $communityPost->id,
                'user_id' => $userId,
                'dedupe' => $dedupeKey,
                'type' => $validated['type'],
                'active' => $active,
            ]);

            return response()->json([
                'success' => true,
                'type' => $validated['type'],
                'active' => $active,
                'counts' => [
                    'likes_count' => (int) $communityPost->likes_count,
                    'shares_count' => (int) $communityPost->shares_count,
                    'bookmarks_count' => (int) $communityPost->bookmarks_count,
                    'comments_count' => (int) $communityPost->comments_count,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('community.react.validation', [
                'post_id' => $communityPost->id,
                'errors' => $e->errors(),
            ]);
            throw $e;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            Log::warning('community.react.http', [
                'post_id' => $communityPost->id,
                'status' => $e->getStatusCode(),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        } catch (\Throwable $e) {
            Log::error('community.react.failed', [
                'post_id' => $communityPost->id,
                'user_id' => $request->user()?->id,
                'message' => $e->getMessage(),
                'exception' => $e::class,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to update reaction.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function comments(CommunityPost $communityPost): JsonResponse
    {
        if (! $communityPost->is_published) {
            return response()->json([
                'comments' => [],
                'error' => 'Community post not found.',
            ], 404);
        }

        $comments = CommunityComment::query()
            ->where('community_post_id', $communityPost->id)
            ->with('user')
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (CommunityComment $comment) => [
                'id' => $comment->id,
                'author_name' => $comment->author_name ?: 'Community member',
                'author_avatar_url' => $comment->user?->avatar_url,
                'content' => $comment->content,
                'created_at' => optional($comment->created_at)->toIso8601String(),
            ]);

        return response()->json(['comments' => $comments]);
    }

    public function addComment(Request $request, CommunityPost $communityPost): JsonResponse
    {
        if (! $communityPost->is_published) {
            return response()->json([
                'error' => 'Community post not found.',
            ], 404);
        }

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:2000'],
        ]);

        $comment = DB::transaction(function () use ($request, $communityPost, $validated) {
            $comment = CommunityComment::query()->create([
                'community_post_id' => $communityPost->id,
                'user_id' => $request->user()?->id,
                'author_name' => trim(($request->user()?->first_name ?? '').' '.($request->user()?->last_name ?? '')) ?: 'Community member',
                'content' => $validated['content'],
            ]);

            $communityPost->increment('comments_count');

            return $comment;
        });
        $comment->load('user');

        $communityPost->refresh();

        return response()->json([
            'success' => true,
            'comment' => [
                'id' => $comment->id,
                'author_name' => $comment->author_name ?: 'Community member',
                'author_avatar_url' => $comment->user?->avatar_url,
                'content' => $comment->content,
                'created_at' => optional($comment->created_at)->toIso8601String(),
            ],
            'counts' => [
                'comments_count' => (int) $communityPost->comments_count,
            ],
        ], 201);
    }

    public function news(Request $request): JsonResponse
    {
        $country = strtoupper((string) $request->query('country', 'US'));
        if (! in_array($country, ['US', 'EU', 'CA', 'GB'], true)) {
            $country = 'US';
        }

        $limit = (int) $request->query('limit', 10);
        if ($limit < 1) {
            $limit = 10;
        }
        if ($limit > 20) {
            $limit = 20;
        }

        $cacheKey = "community.news.{$country}.{$limit}";
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return response()->json($cached);
        }

        try {
            $feedUrl = $this->newsFeedUrl($country);
            $response = \Illuminate\Support\Facades\Http::timeout(10)->get($feedUrl);
            if (! $response->ok()) {
                return response()->json([
                    'country' => $country,
                    'items' => [],
                    'error' => "Unable to fetch feed ({$response->status()})",
                ]);
            }

            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($response->body(), 'SimpleXMLElement', LIBXML_NOCDATA);
            if (! $xml || ! isset($xml->channel->item)) {
                return response()->json([
                    'country' => $country,
                    'items' => [],
                    'error' => 'Unable to parse feed data.',
                ]);
            }

            $items = [];
            $count = 0;
            foreach ($xml->channel->item as $item) {
                if ($count >= $limit) {
                    break;
                }

                $title = $this->cleanFeedText((string) ($item->title ?? ''));
                $url = (string) ($item->link ?? '');
                $publishedAtRaw = (string) ($item->pubDate ?? '');
                $publishedAt = '';
                if ($publishedAtRaw !== '') {
                    try {
                        $publishedAt = \Carbon\Carbon::parse($publishedAtRaw)->toIso8601String();
                    } catch (\Throwable) {
                        $publishedAt = '';
                    }
                }

                $source = '';
                if (isset($item->source)) {
                    $source = $this->cleanFeedText((string) $item->source);
                }
                if ($source === '') {
                    $source = 'Google News';
                }

                $description = (string) ($item->description ?? '');
                $summary = $this->cleanFeedText($description);
                $summaryWords = preg_split('/\s+/', $summary, -1, PREG_SPLIT_NO_EMPTY);
                $summary = implode(' ', array_slice($summaryWords ?: [], 0, 40));

                $image = $this->extractImageFromDescription($description);
                $id = md5($url.$title.$publishedAt);

                $items[] = [
                    'id' => $id,
                    'title' => $title,
                    'url' => $url,
                    'published_at' => $publishedAt,
                    'source' => $source,
                    'summary' => $summary,
                    'image' => $image,
                ];
                $count++;
            }

            $payload = [
                'country' => $country,
                'items' => $items,
            ];
            Cache::put($cacheKey, $payload, now()->addMinutes(10));

            return response()->json($payload);
        } catch (\Throwable $exception) {
            Log::warning('community.news.failed', [
                'country' => $country,
                'limit' => $limit,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'country' => $country,
                'items' => [],
                'error' => 'Unable to load immigration news right now.',
            ]);
        }
    }

    private function newsFeedUrl(string $country): string
    {
        $qBase = urlencode('(immigration OR immigrants)');

        if ($country === 'CA') {
            return "https://news.google.com/rss/search?q={$qBase}&hl=en-CA&gl=CA&ceid=CA:en";
        }
        if ($country === 'GB') {
            return "https://news.google.com/rss/search?q={$qBase}&hl=en-GB&gl=GB&ceid=GB:en";
        }
        if ($country === 'EU') {
            $qEu = urlencode('(immigration OR immigrants) (Europe OR "European Union" OR EU)');

            return "https://news.google.com/rss/search?q={$qEu}&hl=en-GB&gl=GB&ceid=GB:en";
        }

        return "https://news.google.com/rss/search?q={$qBase}&hl=en-US&gl=US&ceid=US:en";
    }

    private function cleanFeedText(string $value): string
    {
        $stripped = preg_replace('/<[^>]+>/', ' ', $value) ?? '';
        $normalized = preg_replace('/\s+/', ' ', $stripped) ?? '';

        return trim($normalized);
    }

    private function extractImageFromDescription(string $description): string
    {
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $description, $matches)) {
            return $matches[1] ?? '';
        }

        return '';
    }

    private function canQueryReactionDedupe(): bool
    {
        return Schema::hasTable('community_post_reactions')
            && Schema::hasColumn('community_post_reactions', 'dedupe_key');
    }
}
