<?php

namespace App\Http\Controllers;

use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\CommunityPostReaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CommunityController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Community/Index');
    }

    public function posts(Request $request): JsonResponse
    {
        Log::info('community.posts.request', [
            'path' => $request->path(),
            'query' => $request->query(),
            'user_id' => $request->user()?->id,
            'ip' => $request->ip(),
        ]);

        try {
            $query = CommunityPost::query()->published()->latest();

            if ($request->filled('category') && $request->string('category') !== 'feed') {
                $query->where('category', $request->string('category'));
            }

            if ($request->filled('search')) {
                $term = '%'.$request->string('search').'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('title', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhere('tag', 'like', $term);
                });
            }

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
            if ($dedupeKey && $items->isNotEmpty()) {
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
            ]);

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
        abort_unless($communityPost->is_published, 404);

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
            abort_unless($communityPost->is_published, 404);

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
        abort_unless($communityPost->is_published, 404);

        $comments = CommunityComment::query()
            ->where('community_post_id', $communityPost->id)
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (CommunityComment $comment) => [
                'id' => $comment->id,
                'author_name' => $comment->author_name ?: 'Community member',
                'content' => $comment->content,
                'created_at' => optional($comment->created_at)->toIso8601String(),
            ]);

        return response()->json(['comments' => $comments]);
    }

    public function addComment(Request $request, CommunityPost $communityPost): JsonResponse
    {
        abort_unless($communityPost->is_published, 404);

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

        $communityPost->refresh();

        return response()->json([
            'success' => true,
            'comment' => [
                'id' => $comment->id,
                'author_name' => $comment->author_name ?: 'Community member',
                'content' => $comment->content,
                'created_at' => optional($comment->created_at)->toIso8601String(),
            ],
            'counts' => [
                'comments_count' => (int) $communityPost->comments_count,
            ],
        ], 201);
    }
}
