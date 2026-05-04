<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lead = $this->resource;

        return [
            'id' => $lead->id,
            'uuid' => $lead->uuid,
            'status' => $lead->status?->value ?? $lead->status,
            'service_type' => $lead->service_type,
            'message' => $lead->message,
            'urgency' => $lead->urgency,
            'needed_by' => optional($lead->needed_by)?->toDateString(),
            'budget_range' => $lead->budget_range,
            'preferred_contact_method' => $lead->preferred_contact_method,
            'preferred_contact_time' => $lead->preferred_contact_time,
            'created_at' => optional($lead->created_at)->toIso8601String(),
            'viewed_at' => optional($lead->viewed_at)->toIso8601String(),
            'responded_at' => optional($lead->responded_at)->toIso8601String(),
            'contract_sent_at' => optional($lead->contract_sent_at)->toIso8601String(),
            'contract_accepted_at' => optional($lead->contract_accepted_at)->toIso8601String(),
            'contract_offered_rate' => $lead->contract_offered_rate,
            'contract_agreed_rate' => $lead->contract_agreed_rate,
            'provider_notes' => $lead->provider_notes,
            'decline_reason' => $lead->decline_reason,
            'user' => $lead->relationLoaded('user') ? [
                'id' => $lead->user?->id,
                'first_name' => $lead->user?->first_name,
                'last_name' => $lead->user?->last_name,
                'email' => $lead->user?->email,
                'phone' => $lead->user?->phone,
                'avatar_url' => $lead->user?->avatar_url,
                'city' => $lead->user?->city,
                'state' => $lead->user?->state,
                'preferred_language' => $lead->user?->preferred_language,
            ] : null,
            'conversation' => $lead->relationLoaded('conversation') && $lead->conversation ? [
                'uuid' => $lead->conversation->uuid,
            ] : null,
        ];
    }
}
