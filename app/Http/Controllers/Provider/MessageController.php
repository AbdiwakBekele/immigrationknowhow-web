<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MessageController extends Controller
{
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
            ->paginate(20);

        $totalUnread = Conversation::query()
            ->forUser($user)
            ->forServiceInquiries()
            ->whereHas('messages', function ($q) use ($user) {
                $q->where('sender_id', '!=', $user->id)
                    ->whereDoesntHave('reads', function ($rq) use ($user) {
                        $rq->where('user_id', $user->id);
                    });
            })
            ->count();

        return Inertia::render('Provider/Messages/Index', [
            'conversations' => $conversations,
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
            'lead:id,service_type,status,message,urgency,created_at',
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
}
