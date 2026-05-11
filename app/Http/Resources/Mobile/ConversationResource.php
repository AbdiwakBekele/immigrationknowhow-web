<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $c = $this->resource;

        return [
            'uuid' => $c->uuid,
            'subject' => $c->subject,
            'last_message_at' => optional($c->last_message_at)->toIso8601String(),
            'unread_count' => (int) ($c->unread_count ?? $c->unreadCount ?? 0),
            'lead' => $c->relationLoaded('lead') && $c->lead ? [
                'uuid' => $c->lead->uuid,
                'service_type' => $c->lead->service_type,
                'status' => $c->lead->status?->value ?? $c->lead->status,
                'urgency' => $c->lead->urgency,
                'created_at' => optional($c->lead->created_at)->toIso8601String(),
                'contract_sent_at' => optional($c->lead->contract_sent_at)->toIso8601String(),
                'contract_accepted_at' => optional($c->lead->contract_accepted_at)->toIso8601String(),
                'contract_uuid' => $c->lead->relationLoaded('contract') ? $c->lead->contract?->uuid : null,
            ] : null,
            'user' => $c->relationLoaded('user') ? [
                'id' => $c->user?->id,
                'first_name' => $c->user?->first_name,
                'last_name' => $c->user?->last_name,
                'avatar_url' => $c->user?->avatar_url,
            ] : null,
            'provider_user' => $c->relationLoaded('serviceProvider') && $c->serviceProvider?->relationLoaded('user') ? [
                'id' => $c->serviceProvider->user?->id,
                'first_name' => $c->serviceProvider->user?->first_name,
                'last_name' => $c->serviceProvider->user?->last_name,
                'avatar_url' => $c->serviceProvider->user?->avatar_url,
            ] : null,
            'latest_message' => $c->relationLoaded('latestMessage') && $c->latestMessage ? [
                'body' => $c->latestMessage->body,
                'created_at' => optional($c->latestMessage->created_at)->toIso8601String(),
            ] : null,
        ];
    }
}
