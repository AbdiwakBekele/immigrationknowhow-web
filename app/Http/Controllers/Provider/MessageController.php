<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class MessageController extends Controller
{
    public function unreadCount(): JsonResponse
    {
        $user = auth()->user();
        abort_unless($user?->serviceProvider, 403);

        return response()->json([
            'count' => Message::unreadIncomingCountFor($user),
        ]);
    }

    public function index(Request $request): Response
    {
        $user = auth()->user();
        $provider = $user->serviceProvider;

        abort_unless($provider, 404);

        $conversations = Conversation::query()
            ->forUser($user)
            ->forServiceInquiries()
            ->with([
                'user:id,first_name,last_name,avatar',
                'serviceProvider.user:id,first_name,last_name,avatar',
                'latestMessage',
                'lead:id,service_type,status',
            ])
            ->withCount(['messages as unread_count' => function ($q) use ($user) {
                $q->where('sender_id', '!=', $user->id)
                    ->whereDoesntHave('reads', function ($rq) use ($user) {
                        $rq->where('user_id', $user->id);
                    });
            }])
            ->orderByDesc('last_message_at')
            ->get();

        $mergedConversations = $this->mergeByParticipants($conversations);
        $perPage = 20;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $items = $mergedConversations->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $paginatedConversations = new LengthAwarePaginator(
            $items,
            $mergedConversations->count(),
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        $totalUnread = $mergedConversations->sum('unread_count');

        return Inertia::render('Provider/Messages/Index', [
            'conversations' => $paginatedConversations,
            'totalUnread' => $totalUnread,
        ]);
    }

    public function show(Conversation $conversation): Response
    {
        $user = auth()->user();
        abort_unless($user->serviceProvider, 404);

        $this->authorize('view', $conversation);

        $conversation->load([
            'user:id,first_name,last_name,avatar',
            'serviceProvider.user:id,first_name,last_name,avatar',
            'lead:id,uuid,service_type,status,message,urgency,created_at,contract_sent_at,contract_accepted_at',
            'messages' => fn ($q) => $q->with('sender:id,first_name,last_name,avatar')->orderBy('created_at', 'asc'),
        ]);

        $conversation->markAllAsRead($user);

        $isProvider = $user->serviceProvider?->id === $conversation->service_provider_id;

        return Inertia::render('Provider/Messages/Show', [
            'conversation' => $conversation,
            'isProvider' => $isProvider,
            'otherParticipant' => $isProvider
                ? $conversation->user
                : $conversation->serviceProvider->user,
        ]);
    }

    public function archived(): Response
    {
        $user = auth()->user();
        abort_unless($user->serviceProvider, 404);

        $conversations = Conversation::query()
            ->forServiceInquiries()
            ->where(function ($q) use ($user) {
                $q->where(function ($owningUserQuery) use ($user) {
                    $owningUserQuery->where('user_id', $user->id)
                        ->where('user_archived', true);
                })->orWhere(function ($providerQuery) use ($user) {
                    $providerQuery->where('service_provider_id', $user->serviceProvider->id)
                        ->where('provider_archived', true);
                });
            })
            ->with([
                'user:id,first_name,last_name,avatar',
                'serviceProvider.user:id,first_name,last_name,avatar',
                'latestMessage',
            ])
            ->orderByDesc('last_message_at')
            ->paginate(20);

        return Inertia::render('Provider/Messages/Archived', [
            'conversations' => $conversations,
        ]);
    }

    protected function mergeByParticipants(Collection $conversations): Collection
    {
        return $conversations
            ->groupBy(fn (Conversation $conversation) => $conversation->user_id.'-'.$conversation->service_provider_id)
            ->map(function (Collection $group) {
                /** @var Conversation $latest */
                $latest = $group->sortByDesc('last_message_at')->first();
                $latest->setAttribute('unread_count', (int) $group->sum('unread_count'));

                return $latest;
            })
            ->sortByDesc('last_message_at')
            ->values();
    }
}
