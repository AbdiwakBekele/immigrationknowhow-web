<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $r = $this->resource;

        return [
            'uuid' => $r->uuid,
            'rating' => $r->rating,
            'body' => $r->body,
            'created_at' => optional($r->created_at)->toIso8601String(),
            'user' => $r->relationLoaded('user') ? [
                'first_name' => $r->user?->first_name,
                'last_name' => $r->user?->last_name,
                'avatar_url' => $r->user?->avatar_url,
            ] : null,
        ];
    }
}
