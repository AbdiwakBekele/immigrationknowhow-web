<?php

namespace App\Http\Controllers;

use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\CommunityPostReaction;
use App\Support\PublicCommunityPostPresentation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PublicCommunityApiController extends Controller
{
    public function posts(Request $request): JsonResponse
    {
        if (! Schema::hasTable('community_posts')) {
            return response()->json([
                'posts' => $this->emptyPaginatorPayload($request),
            ]);
        }

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'category' => ['nullable', 'string', 'max:64'],
            'search' => ['nullable', 'string', 'max:200'],
            'has_video' => ['nullable', 'boolean'],
            'guest_key' => ['nullable', 'uuid'],
        ]);

        $category = trim((string) ($validated['category'] ?? 'feed'));
        if ($category === '') {
            $category = 'feed';
        }

        $search = trim((string) ($validated['search'] ?? ''));
        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = CommunityPost::query()
            ->published()
            ->with('contributor:id,first_name,last_name,email,country')
            ->latest();

        if ($category !== 'feed') {
            $query->where('category', $category);
        }

        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function ($inner) use ($term): void {
                $inner->where('title', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('tag', 'like', $term);
            });
        }

        if ($request->boolean('has_video')) {
            $query->whereNotNull('video_url')->where('video_url', '!=', '');
        }

        $paginated = $query->paginate($perPage);
        $items = $paginated->getCollection();
        $reactionMap = $this->reactionMapForPosts($request, $items->pluck('id')->all());

        $posts = $paginated->through(
            fn (CommunityPost $post) => PublicCommunityPostPresentation::payload(
                $post,
                $reactionMap[$post->id] ?? [],
            ),
        );

        return response()->json(['posts' => $posts]);
    }

    public function show(Request $request, CommunityPost $communityPost): JsonResponse
    {
        if (! $communityPost->is_published) {
            return response()->json([
                'error' => 'Community post not found.',
            ], 404);
        }

        $communityPost->loadMissing('contributor:id,first_name,last_name,email,country');

        $userReactions = [];
        $dedupeKey = $this->dedupeKey($request);
        if ($dedupeKey) {
            $userReactions = CommunityPostReaction::query()
                ->where('dedupe_key', $dedupeKey)
                ->where('community_post_id', $communityPost->id)
                ->pluck('type')
                ->values()
                ->all();
        }

        return response()->json([
            'post' => PublicCommunityPostPresentation::payload($communityPost, $userReactions),
        ]);
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

    public function news(Request $request, CommunityController $community): JsonResponse
    {
        return $community->news($request);
    }

    /**
     * @param  array<int, int>  $postIds
     * @return array<int, array<int, string>>
     */
    private function reactionMapForPosts(Request $request, array $postIds): array
    {
        $dedupeKey = $this->dedupeKey($request);
        if ($dedupeKey === null || $postIds === [] || ! $this->canQueryReactionDedupe()) {
            return [];
        }

        return CommunityPostReaction::query()
            ->where('dedupe_key', $dedupeKey)
            ->whereIn('community_post_id', $postIds)
            ->get()
            ->groupBy('community_post_id')
            ->map(fn ($rows) => $rows->pluck('type')->values()->all())
            ->toArray();
    }

    private function dedupeKey(Request $request): ?string
    {
        $userId = $request->user()?->id;
        if ($userId) {
            return 'u'.$userId;
        }

        $guestKey = $request->query('guest_key');
        if (is_string($guestKey) && $guestKey !== '' && Str::isUuid($guestKey)) {
            return 'g'.Str::lower($guestKey);
        }

        return null;
    }

    private function canQueryReactionDedupe(): bool
    {
        return Schema::hasTable('community_post_reactions')
            && Schema::hasColumn('community_post_reactions', 'dedupe_key');
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPaginatorPayload(Request $request): array
    {
        return [
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
        ];
    }
}
