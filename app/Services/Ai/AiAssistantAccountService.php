<?php

namespace App\Services\Ai;

use App\Models\AiAssistantMessage;
use App\Models\AiAssistantSubscription;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * AI Assistant is one subscription per login account (shared by seeker + provider portals).
 */
class AiAssistantAccountService
{
    /** @var list<string> */
    public const CHAT_CONTEXTS = ['mobile', 'user', 'provider'];

    public function subscriptionForUser(int $userId): ?AiAssistantSubscription
    {
        if (! Schema::hasTable('ai_assistant_subscriptions')) {
            return null;
        }

        return AiAssistantSubscription::forUser($userId);
    }

    public function isSubscribed(int $userId): bool
    {
        return $this->subscriptionForUser($userId)?->isActive() ?? false;
    }

    public function isSubscribedUser(User $user): bool
    {
        return $this->isSubscribed((int) $user->id);
    }

    /**
     * @return Collection<int, array{id: string, role: string, text: string, ts: string|null}>
     */
    public function chatMessagesForUser(int $userId, int $limit = 60): Collection
    {
        if (! Schema::hasTable('ai_assistant_messages')) {
            return collect();
        }

        return AiAssistantMessage::query()
            ->where('user_id', $userId)
            ->whereIn('context', self::CHAT_CONTEXTS)
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'role', 'content', 'created_at'])
            ->map(fn ($m) => [
                'id' => (string) $m->id,
                'role' => $m->role,
                'text' => $m->content,
                'ts' => optional($m->created_at)->toISOString(),
            ])
            ->values();
    }

    public function resolveMessageContext(User $user, ?string $portalHeader = null): string
    {
        $portal = is_string($portalHeader) ? strtolower(trim($portalHeader)) : '';

        if ($portal === 'provider' && $user->isProvider()) {
            return 'provider';
        }

        if ($portal === 'user' && $user->hasRole('user')) {
            return 'user';
        }

        return 'mobile';
    }
}
