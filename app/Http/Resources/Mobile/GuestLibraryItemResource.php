<?php

namespace App\Http\Resources\Mobile;

use App\Models\LibraryItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuestLibraryItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var LibraryItem $item */
        $item = $this->resource;
        $regions = is_array($item->regions) ? $item->regions : [];
        $countries = collect($regions)
            ->map(fn ($region) => LibraryItem::REGION_DEFINITIONS[$region] ?? null)
            ->filter()
            ->values()
            ->all();

        return [
            'slug' => $item->slug,
            'title' => $item->title,
            'cover_image_url' => $item->cover_image_url,
            'category' => $item->category ? [
                'name' => $item->category->name,
                'slug' => $item->category->slug,
            ] : null,
            'countries' => $countries,
        ];
    }
}
