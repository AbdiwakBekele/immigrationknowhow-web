<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\ConversationResource;
use App\Http\Resources\Mobile\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessagesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = Conversation::query()
            ->forUser($user)
            ->forServiceInquiries()
            ->with([
                'user:id,first_name,last_name,avatar',
                'serviceProvider.user:id,first_name,last_name,avatar',
                'latestMessage',
                'lead:id,uuid,service_type,status',
            ])
            ->withCount(['messages as unread_count' => function ($q) use ($user) {
                $q->where('sender_id', '!=', $user->id)
                    ->whereDoesntHave('reads', function ($rq) use ($user) {
                        $rq->where('user_id', $user->id);
                    });
            }])
            ->orderByDesc('last_message_at')
            ->get();

        return $this->success('OK', [
            'conversations' => ConversationResource::collection($conversations)->resolve(),
        ]);
    }

    public function archived(Request $request): JsonResponse
    {
        $user = $request->user();

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
                'lead:id,uuid,service_type,status',
            ])
            ->orderByDesc('last_message_at')
            ->get();

        return $this->success('OK', [
            'conversations' => ConversationResource::collection($conversations)->resolve(),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success('OK', [
            'count' => Message::unreadIncomingCountFor($user),
        ]);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $conversation->load([
            'user:id,first_name,last_name,avatar',
            'serviceProvider.user:id,first_name,last_name,avatar',
            'lead:id,uuid,service_type,status,urgency,created_at,contract_sent_at,contract_accepted_at',
            'lead.contract:id,uuid,lead_id',
            'messages' => fn ($q) => $q->with('sender:id,first_name,last_name,avatar')->orderBy('created_at', 'asc'),
        ]);

        $conversation->markAllAsRead($request->user());

        return $this->success('OK', [
            'conversation' => (new ConversationResource($conversation))->resolve(),
            'messages' => MessageResource::collection($conversation->messages)->resolve(),
        ]);
    }

    public function send(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('update', $conversation);

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:5000'],
        ]);

        $message = $conversation->addMessage($request->user(), $validated['body']);

        $message->load('sender:id,first_name,last_name,avatar');

        return $this->success('Message sent', [
            'message' => (new MessageResource($message))->resolve(),
        ]);
    }

    public function markAsRead(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $conversation->markAllAsRead($request->user());

        return $this->success('OK', []);
    }

    public function archive(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('update', $conversation);

        $conversation->archive($request->user());

        return $this->success('Archived', []);
    }

    public function unarchive(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('update', $conversation);

        $conversation->unarchive($request->user());

        return $this->success('Restored', []);
    }

    public function destroy(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('update', $conversation);

        // Web allows delete for participants (archive+delete). Keep same behavior.
        $conversation->delete();

        return $this->success('Deleted', []);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function success(string $message, array $data, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }
}
