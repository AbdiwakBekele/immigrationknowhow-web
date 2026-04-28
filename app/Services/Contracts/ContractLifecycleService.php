<?php

namespace App\Services\Contracts;

use App\Enums\ContractState;
use App\Enums\LeadStatus;
use App\Models\Contract;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ContractLifecycleService
{
    public function ensureForLead(Lead $lead): Contract
    {
        return DB::transaction(function () use ($lead) {
            $lead->loadMissing('conversation');

            $contract = $lead->contract ?: Contract::query()->firstOrCreate(
                ['lead_id' => $lead->id],
                [
                    'conversation_id' => $lead->conversation?->id,
                    'service_provider_id' => $lead->service_provider_id,
                    'user_id' => $lead->user_id,
                    'pricing_model' => $lead->serviceProvider?->pricing_model,
                    'currency' => 'USD',
                    'state' => ContractState::DRAFT,
                ]
            );

            if (! $lead->contract_id || $lead->contract_id !== $contract->id) {
                $lead->forceFill(['contract_id' => $contract->id])->saveQuietly();
            }

            return $contract->refresh();
        });
    }

    public function offer(Lead $lead, User $actor, ?float $offeredRate = null): Contract
    {
        return DB::transaction(function () use ($lead, $actor, $offeredRate) {
            $contract = $this->ensureForLead($lead);
            $contract = Contract::query()->lockForUpdate()->findOrFail($contract->id);

            if ($contract->state === ContractState::ACCEPTED) {
                throw new RuntimeException('Offer already accepted.');
            }

            $rate = $offeredRate;
            if ($rate === null) {
                $rate = $lead->serviceProvider?->hourly_rate !== null
                    ? (float) $lead->serviceProvider->hourly_rate
                    : null;
            }

            $fromState = $contract->state;
            $contract->fill([
                'offered_rate' => $rate !== null ? round($rate, 2) : null,
                'agreed_rate' => null,
                'state' => ContractState::OFFERED,
                'offered_at' => now(),
                'withdrawn_at' => null,
                'version' => $contract->version + 1,
            ])->save();

            $this->recordEvent($contract, $actor, 'offer_sent', $fromState, ContractState::OFFERED, [
                'offered_rate' => $contract->offered_rate,
            ]);

            $leadUpdates = [
                'status' => LeadStatus::CONTACTED,
                'responded_at' => $lead->responded_at ?? now(),
                'contract_id' => $contract->id,
            ];
            if ((bool) config('contracts.keep_legacy_dual_write', true)) {
                $leadUpdates = [
                    ...$leadUpdates,
                    'contract_sent_at' => $contract->offered_at,
                    'contract_accepted_at' => null,
                    'contract_offered_rate' => $contract->offered_rate,
                    'contract_agreed_rate' => null,
                ];
            }
            $lead->update($leadUpdates);

            return $contract->refresh();
        });
    }

    public function withdraw(Contract $contract, User $actor): Contract
    {
        return DB::transaction(function () use ($contract, $actor) {
            $contract = Contract::query()->lockForUpdate()->findOrFail($contract->id);

            if ($contract->state !== ContractState::OFFERED) {
                throw new RuntimeException('Only pending offers can be withdrawn.');
            }

            $fromState = $contract->state;
            $contract->fill([
                'state' => ContractState::WITHDRAWN,
                'withdrawn_at' => now(),
                'version' => $contract->version + 1,
            ])->save();

            $this->recordEvent($contract, $actor, 'offer_withdrawn', $fromState, ContractState::WITHDRAWN);

            if ((bool) config('contracts.keep_legacy_dual_write', true)) {
                $contract->lead->update([
                    'contract_sent_at' => null,
                    'contract_accepted_at' => null,
                    'contract_offered_rate' => null,
                    'contract_agreed_rate' => null,
                ]);
            }

            return $contract->refresh();
        });
    }

    public function accept(Contract $contract, User $actor): Contract
    {
        return DB::transaction(function () use ($contract, $actor) {
            $contract = Contract::query()->lockForUpdate()->findOrFail($contract->id);

            if ($contract->state !== ContractState::OFFERED) {
                throw new RuntimeException('Only pending offers can be accepted.');
            }

            $fromState = $contract->state;
            $contract->fill([
                'state' => ContractState::ACCEPTED,
                'accepted_at' => now(),
                'agreed_rate' => $contract->offered_rate,
                'version' => $contract->version + 1,
            ])->save();

            $this->recordEvent($contract, $actor, 'offer_accepted', $fromState, ContractState::ACCEPTED, [
                'agreed_rate' => $contract->agreed_rate,
            ]);

            $leadUpdates = [
                'status' => LeadStatus::IN_PROGRESS,
            ];
            if ((bool) config('contracts.keep_legacy_dual_write', true)) {
                $leadUpdates = [
                    ...$leadUpdates,
                    'contract_accepted_at' => $contract->accepted_at,
                    'contract_agreed_rate' => $contract->agreed_rate,
                ];
            }
            $contract->lead->update($leadUpdates);

            return $contract->refresh();
        });
    }

    public function end(Contract $contract, User $actor, ?string $reason = null): Contract
    {
        return DB::transaction(function () use ($contract, $actor, $reason) {
            $contract = Contract::query()->lockForUpdate()->findOrFail($contract->id);

            if (! in_array($contract->state, [ContractState::ACCEPTED, ContractState::IN_PROGRESS, ContractState::COMPLETED], true)) {
                throw new RuntimeException('Only accepted or active contracts can be ended.');
            }

            $fromState = $contract->state;
            $contract->fill([
                'state' => ContractState::ENDED,
                'ended_at' => now(),
                'ended_reason' => $reason,
                'version' => $contract->version + 1,
            ])->save();

            $this->recordEvent($contract, $actor, 'contract_ended', $fromState, ContractState::ENDED, [
                'reason' => $reason,
            ]);

            $contract->lead->update([
                'status' => LeadStatus::CLOSED,
                'closed_at' => now(),
            ]);

            return $contract->refresh();
        });
    }

    protected function recordEvent(
        Contract $contract,
        ?User $actor,
        string $eventType,
        ?ContractState $fromState,
        ?ContractState $toState,
        array $payload = []
    ): void {
        $contract->events()->create([
            'actor_id' => $actor?->id,
            'event_type' => $eventType,
            'from_state' => $fromState?->value,
            'to_state' => $toState?->value,
            'payload_json' => $payload,
        ]);
    }
}
