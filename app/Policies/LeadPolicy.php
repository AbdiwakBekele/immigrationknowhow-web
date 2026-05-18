<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;
use App\Support\UserRoleAccounts;

class LeadPolicy
{
    /**
     * Determine if the user can view any leads.
     */
    public function viewAny(User $user): bool
    {
        // Providers can view their leads
        if ($user->isProvider()) {
            return true;
        }

        // Admins can view all leads
        if ($user->isAdmin()) {
            return true;
        }

        // Users can view their own leads
        return true;
    }

    /**
     * Determine if the user can view the lead.
     */
    public function view(User $user, Lead $lead): bool
    {
        // The user who created the lead
        if ($lead->user_id === $user->id) {
            return true;
        }

        // The provider who received the lead
        if ($user->serviceProvider && $lead->service_provider_id === $user->serviceProvider->id) {
            return true;
        }

        // Admins can view all leads
        if ($user->isAdmin()) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can create leads.
     */
    public function create(User $user): bool
    {
        return UserRoleAccounts::canContactServiceProviders($user, request()->header('X-Active-Portal'));
    }

    /**
     * Determine if the user can update the lead.
     */
    public function update(User $user, Lead $lead): bool
    {
        // The provider who received the lead can update it
        if ($user->serviceProvider && $lead->service_provider_id === $user->serviceProvider->id) {
            return true;
        }

        // Admins can update any lead
        if ($user->isAdmin()) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can delete the lead.
     */
    public function delete(User $user, Lead $lead): bool
    {
        // Only admins can delete leads
        return $user->isAdmin();
    }

    /**
     * Determine if the user can respond to the lead.
     */
    public function respond(User $user, Lead $lead): bool
    {
        // The provider who received the lead can respond
        if ($user->serviceProvider && $lead->service_provider_id === $user->serviceProvider->id) {
            return true;
        }

        return false;
    }
}
