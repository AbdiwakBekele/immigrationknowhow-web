<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

/**
 * Messaging requires a signed-in user (routes use auth; this policy assumes that).
 *
 * A conversation belongs to a specific lead. Only that lead’s user (client),
 * the related service provider, and admins may view or send messages.
 */
class ConversationPolicy
{
    /**
     * Determine if the user can view any conversations.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the conversation.
     */
    public function view(User $user, Conversation $conversation): bool
    {
        // Controlled, inquiry-based messaging only (no orphan / open threads)
        if ($conversation->lead_id === null && ! $user->isAdmin()) {
            return false;
        }

        // The user who initiated the conversation
        if ($conversation->user_id === $user->id) {
            return true;
        }

        // The provider involved in the conversation
        if ($user->serviceProvider && $conversation->service_provider_id === $user->serviceProvider->id) {
            return true;
        }

        // Admins can view all conversations
        if ($user->isAdmin()) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can update the conversation (send messages).
     */
    public function update(User $user, Conversation $conversation): bool
    {
        if ($conversation->lead_id === null) {
            return false;
        }

        // The user who initiated the conversation
        if ($conversation->user_id === $user->id) {
            return true;
        }

        // The provider involved in the conversation
        if ($user->serviceProvider && $conversation->service_provider_id === $user->serviceProvider->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can delete the conversation.
     */
    public function delete(User $user, Conversation $conversation): bool
    {
        // Only admins can delete conversations
        return $user->isAdmin();
    }

    /**
     * Determine if the user can archive the conversation.
     */
    public function archive(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }
}
