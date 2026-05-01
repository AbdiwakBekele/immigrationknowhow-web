<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource;

        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'phone_verified_at' => $user->phone_verified_at,
            'country' => $user->country,
            'state' => $user->state,
            'city' => $user->city,
            'onboarding_completed' => (bool) $user->onboarding_completed,
            'roles' => method_exists($user, 'getRoleNames')
                ? $user->getRoleNames()->values()->all()
                : [],
        ];
    }
}
