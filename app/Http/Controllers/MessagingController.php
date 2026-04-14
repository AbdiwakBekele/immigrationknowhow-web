<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Notifications\NewMessageNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class MessagingController extends Controller
{
    public function index(Request $request): Response
    {
        $user = auth()->user();

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

        return Inertia::render('Messages/Index', [
            'conversations' => $paginatedConversations,
            'totalUnread' => $totalUnread,
            'isProvider' => (bool) $user->serviceProvider,
        ]);
    }

    public function show(Conversation $conversation): Response
    {
        $user = auth()->user();

        // Ensure user is part of this conversation
        $this->authorize('view', $conversation);

        // Load conversation with messages
        $conversation->load([
            'user:id,first_name,last_name,avatar',
            'serviceProvider.user:id,first_name,last_name,avatar',
            'lead:id,uuid,service_type,status,message,urgency,created_at,contract_sent_at,contract_accepted_at',
            'messages' => fn ($q) => $q->with('sender:id,first_name,last_name,avatar')->orderBy('created_at', 'asc'),
        ]);

        // Mark all messages as read
        $conversation->markAllAsRead($user);

        // Determine the other participant
        $isProvider = $user->serviceProvider?->id === $conversation->service_provider_id;
        $messageSenderIds = $conversation->messages->pluck('sender_id')->unique();
        $providerUserId = $conversation->serviceProvider?->user_id;
        $hasExchangedMessages = $providerUserId
            ? $messageSenderIds->contains($conversation->user_id) && $messageSenderIds->contains($providerUserId)
            : false;

        $conversation->setAttribute('has_exchanged_messages', $hasExchangedMessages);

        if ($conversation->lead) {
            $conversation->lead->setAttribute(
                'can_send_offer',
                ! $isProvider
                    && in_array($conversation->lead->status, ['new', 'contacted'], true)
                    && is_null($conversation->lead->contract_sent_at)
            );
        }

        return Inertia::render('Messages/Show', [
            'conversation' => $conversation,
            'isProvider' => $isProvider,
            'otherParticipant' => $isProvider
                ? $conversation->user
                : $conversation->serviceProvider->user,
        ]);
    }

    public function sendMessage(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('update', $conversation);

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240'], // 10MB max per file
        ]);

        $user = auth()->user();

        // Handle file uploads
        $attachments = null;
        if ($request->hasFile('attachments')) {
            $attachments = [];
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('message-attachments/'.$conversation->uuid, 'public');
                $attachments[] = [
                    'path' => $path,
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'type' => $file->getMimeType(),
                ];
            }
        }

        $message = $conversation->addMessage($user, $validated['body'], $attachments);

        // Notify the other participant
        $otherUser = $conversation->user_id === $user->id
            ? $conversation->serviceProvider->user
            : $conversation->user;

        $otherUser->notify(new NewMessageNotification($message));

        return back()->with('success', 'Message sent.');
    }

    public function markAsRead(Conversation $conversation): RedirectResponse
    {
        $this->authorize('view', $conversation);

        $conversation->markAllAsRead(auth()->user());

        return back();
    }

    public function archive(Conversation $conversation): RedirectResponse
    {
        $this->authorize('update', $conversation);

        $conversation->archive(auth()->user());

        $indexRoute = auth()->user()->serviceProvider
            ? 'provider.messages.index'
            : 'messages.index';

        return redirect()->route($indexRoute)
            ->with('success', 'Conversation archived.');
    }

    public function unarchive(Conversation $conversation): RedirectResponse
    {
        $this->authorize('update', $conversation);

        $conversation->unarchive(auth()->user());

        $indexRoute = auth()->user()->serviceProvider
            ? 'provider.messages.index'
            : 'messages.index';

        return redirect()->route($indexRoute)
            ->with('success', 'Conversation restored.');
    }

    // Get archived conversations
    public function archived(): Response
    {
        $user = auth()->user();

        $conversations = Conversation::query()
            ->forServiceInquiries()
            ->where(function ($q) use ($user) {
                $q->where(function ($owningUserQuery) use ($user) {
                    $owningUserQuery->where('user_id', $user->id)
                        ->where('user_archived', true);
                });

                if ($user->serviceProvider) {
                    $q->orWhere(function ($providerQuery) use ($user) {
                        $providerQuery->where('service_provider_id', $user->serviceProvider->id)
                            ->where('provider_archived', true);
                    });
                }
            })
            ->with([
                'user:id,first_name,last_name,avatar',
                'serviceProvider.user:id,first_name,last_name,avatar',
                'latestMessage',
            ])
            ->orderByDesc('last_message_at')
            ->paginate(20);

        return Inertia::render('Messages/Archived', [
            'conversations' => $conversations,
        ]);
    }

    public function destroy(Conversation $conversation): RedirectResponse
    {
        $this->authorize('update', $conversation);

        $conversation->delete();

        return back()->with('success', 'Conversation deleted.');
    }

    // Get unread count for navbar
    public function unreadCount(): JsonResponse
    {
        $user = auth()->user();

        $conversations = Conversation::query()
            ->forUser($user)
            ->forServiceInquiries()
            ->withCount(['messages as unread_count' => function ($q) use ($user) {
                $q->where('sender_id', '!=', $user->id)
                    ->whereDoesntHave('reads', function ($rq) use ($user) {
                        $rq->where('user_id', $user->id);
                    });
            }])
            ->get();

        $count = $this->mergeByParticipants($conversations)->sum('unread_count');

        return response()->json(['count' => $count]);
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
