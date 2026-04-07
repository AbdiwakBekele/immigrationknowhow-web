<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Notifications\NewMessageNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MessagingController extends Controller
{
    public function index(Request $request): Response
    {
        $user = auth()->user();

        $conversations = Conversation::query()
            ->forUser($user)
            ->with([
                'user:id,first_name,last_name,avatar',
                'serviceProvider.user:id,first_name,last_name,avatar',
                'latestMessage:id,conversation_id,sender_id,body,created_at',
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

        // Get total unread count
        $totalUnread = Conversation::query()
            ->forUser($user)
            ->whereHas('messages', function ($q) use ($user) {
                $q->where('sender_id', '!=', $user->id)
                    ->whereDoesntHave('reads', function ($rq) use ($user) {
                        $rq->where('user_id', $user->id);
                    });
            })
            ->count();

        return Inertia::render('Messages/Index', [
            'conversations' => $conversations,
            'totalUnread' => $totalUnread,
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
            'lead:id,service_type,status,message,urgency,created_at',
            'messages' => fn($q) => $q->with('sender:id,first_name,last_name,avatar')->orderBy('created_at', 'asc'),
        ]);

        // Mark all messages as read
        $conversation->markAllAsRead($user);

        // Determine the other participant
        $isProvider = $user->serviceProvider?->id === $conversation->service_provider_id;

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
                $path = $file->store('message-attachments/' . $conversation->uuid, 'public');
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

        return redirect()->route('messages.index')
            ->with('success', 'Conversation archived.');
    }

    public function unarchive(Conversation $conversation): RedirectResponse
    {
        $this->authorize('update', $conversation);

        $conversation->unarchive(auth()->user());

        return back()->with('success', 'Conversation restored.');
    }

    // Get archived conversations
    public function archived(): Response
    {
        $user = auth()->user();

        $conversations = Conversation::query()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->where('user_archived', true);
            })
            ->orWhere(function ($q) use ($user) {
                if ($user->serviceProvider) {
                    $q->where('service_provider_id', $user->serviceProvider->id)
                        ->where('provider_archived', true);
                }
            })
            ->with([
                'user:id,first_name,last_name,avatar',
                'serviceProvider.user:id,first_name,last_name,avatar',
                'latestMessage:id,conversation_id,body,created_at',
            ])
            ->orderByDesc('last_message_at')
            ->paginate(20);

        return Inertia::render('Messages/Archived', [
            'conversations' => $conversations,
        ]);
    }

    // Get unread count for navbar
    public function unreadCount(): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();

        $count = Conversation::query()
            ->forUser($user)
            ->whereHas('messages', function ($q) use ($user) {
                $q->where('sender_id', '!=', $user->id)
                    ->whereDoesntHave('reads', function ($rq) use ($user) {
                        $rq->where('user_id', $user->id);
                    });
            })
            ->count();

        return response()->json(['count' => $count]);
    }
}
