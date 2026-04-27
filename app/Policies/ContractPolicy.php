<?php

namespace App\Policies;

use App\Enums\ContractState;
use App\Models\Contract;
use App\Models\User;

class ContractPolicy
{
    public function view(User $user, Contract $contract): bool
    {
        if ($contract->user_id === $user->id) {
            return true;
        }

        if ($user->serviceProvider?->id === $contract->service_provider_id) {
            return true;
        }

        return $user->isAdmin();
    }

    public function offer(User $user, Contract $contract): bool
    {
        return $contract->user_id === $user->id;
    }

    public function withdraw(User $user, Contract $contract): bool
    {
        if ($contract->user_id !== $user->id) {
            return false;
        }

        return $contract->state === ContractState::OFFERED;
    }

    public function accept(User $user, Contract $contract): bool
    {
        if ($user->serviceProvider?->id !== $contract->service_provider_id) {
            return false;
        }

        return $contract->state === ContractState::OFFERED;
    }

    public function end(User $user, Contract $contract): bool
    {
        if ($contract->user_id !== $user->id) {
            return false;
        }

        return in_array($contract->state, [ContractState::ACCEPTED, ContractState::IN_PROGRESS, ContractState::COMPLETED], true);
    }
}
