<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $m = $this->resource;

        return [
            'uuid' => $m->uuid,
            'body' => $m->body,
            'created_at' => optional($m->created_at)->toIso8601String(),
            'is_mine' => (bool) ($m->is_mine ?? false),
            'sender' => $m->relationLoaded('sender') ? [
                'id' => $m->sender?->id,
                'first_name' => $m->sender?->first_name,
                'last_name' => $m->sender?->last_name,
                'avatar_url' => $m->sender?->avatar_url,
            ] : null,
        ];
    }
}
