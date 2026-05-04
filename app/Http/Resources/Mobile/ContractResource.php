<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $c = $this->resource;

        return [
            'uuid' => $c->uuid,
            'state' => $c->state?->value ?? $c->state,
            'pricing_model' => $c->pricing_model,
            'currency' => $c->currency,
            'offered_rate' => $c->offered_rate,
            'agreed_rate' => $c->agreed_rate,
            'offered_at' => optional($c->offered_at)->toIso8601String(),
            'accepted_at' => optional($c->accepted_at)->toIso8601String(),
            'withdrawn_at' => optional($c->withdrawn_at)->toIso8601String(),
            'ended_at' => optional($c->ended_at)->toIso8601String(),
            'ended_reason' => $c->ended_reason,
            'lead' => $c->relationLoaded('lead') && $c->lead ? [
                'uuid' => $c->lead->uuid,
                'status' => $c->lead->status?->value ?? $c->lead->status,
                'service_type' => $c->lead->service_type,
                'message' => $c->lead->message,
                'contract_sent_at' => optional($c->lead->contract_sent_at)->toIso8601String(),
                'contract_accepted_at' => optional($c->lead->contract_accepted_at)->toIso8601String(),
            ] : null,
            'provider' => $c->relationLoaded('serviceProvider') && $c->serviceProvider ? [
                'slug' => $c->serviceProvider->slug,
                'business_name' => $c->serviceProvider->business_name,
            ] : null,
        ];
    }
}
