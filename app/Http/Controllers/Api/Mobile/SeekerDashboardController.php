<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\ConversationResource;
use App\Http\Resources\Mobile\ProviderResource;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\LibraryItem;
use App\Models\LibraryUserAccess;
use App\Models\Message;
use App\Models\ServiceProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SeekerDashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $favoritesEnabled = Schema::hasTable('provider_favorites');
        $favoriteProviderIds = $favoritesEnabled
            ? $user->favoriteServiceProviders()->pluck('service_providers.id')->all()
            : [];
        $favoriteSet = array_flip($favoriteProviderIds);

        $stats = [
            'totalLeads' => Lead::where('user_id', $user->id)->count(),
            'unreadMessages' => Message::unreadIncomingCountFor($user),
            'profileCompletion' => $this->calculateProfileCompletion($user),
        ];

        $recentLeads = Lead::where('user_id', $user->id)
            ->whereHas('conversation', function ($q) use ($user) {
                $q->forUser($user)->forServiceInquiries();
            })
            ->with([
                'serviceProvider:id,slug,business_name',
                'serviceProvider.user:id,first_name,last_name,avatar',
                'conversation:id,lead_id,uuid',
            ])
            ->latest()
            ->limit(5)
            ->get();

        $recentMessages = Conversation::query()
            ->forUser($user)
            ->forServiceInquiries()
            ->with([
                'user:id,first_name,last_name,avatar',
                'serviceProvider.user:id,first_name,last_name,avatar',
                'latestMessage',
                'lead:id,uuid,service_type,status',
            ])
            ->orderByDesc('last_message_at')
            ->limit(5)
            ->get();

        $recommendedProviders = $this->getRecommendedProviders($user)
            ->map(function (ServiceProvider $provider) use ($favoriteSet) {
                $provider->setAttribute('is_favorited', isset($favoriteSet[$provider->id]));

                return $provider;
            });

        $savedProviders = collect();
        if ($favoritesEnabled) {
            $savedProviders = $user->favoriteServiceProviders()
                ->with(['user:id,first_name,last_name,avatar,city,state,country'])
                ->active()
                ->acceptingClients()
                ->exceptOwnListing($user)
                ->whereUserCountry($user->country)
                ->limit(6)
                ->get()
                ->map(function (ServiceProvider $provider) {
                    $provider->setAttribute('is_favorited', true);

                    return $provider;
                })
                ->values();
        }

        $libraryItems = LibraryItem::active()
            ->featured()
            ->with('libraryAuthor')
            ->limit(3)
            ->get([
                'uuid',
                'slug',
                'title',
                'author_id',
                'type',
                'cover_image',
                'is_premium',
                'price',
                'currency',
            ]);

        $purchasedItems = LibraryUserAccess::query()
            ->where('user_id', $user->id)
            ->whereNotNull('purchased_at')
            ->with(['libraryItem.libraryAuthor'])
            ->latest('purchased_at')
            ->limit(5)
            ->get()
            ->map(function (LibraryUserAccess $access) {
                $item = $access->libraryItem;
                if (! $item || ! $item->is_active) {
                    return null;
                }

                return [
                    'access_id' => $access->id,
                    'purchased_at' => optional($access->purchased_at)->toIso8601String(),
                    'purchase_amount' => $access->purchase_amount,
                    'purchase_currency' => $access->purchase_currency,
                    'item' => [
                        'uuid' => $item->uuid,
                        'slug' => $item->slug,
                        'title' => $item->title,
                        'author' => $item->author,
                        'type' => $item->type,
                        'cover_image_url' => $item->cover_image_url,
                        'is_premium' => $item->is_premium,
                        'price' => $item->price !== null ? (float) $item->price : null,
                        'currency' => $item->currency,
                    ],
                ];
            })
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'stats' => $stats,
                'recent_leads' => $recentLeads,
                'recent_messages' => ConversationResource::collection($recentMessages)->resolve(),
                'recommended_providers' => ProviderResource::collection($recommendedProviders)->resolve(),
                'saved_providers' => ProviderResource::collection($savedProviders)->resolve(),
                'library_items' => $libraryItems,
                'purchased_items' => $purchasedItems,
            ],
        ]);
    }

    protected function calculateProfileCompletion($user): int
    {
        $fields = [
            'first_name',
            'last_name',
            'email',
            'phone',
            'city',
            'state',
            'country',
            'languages',
            'avatar',
        ];

        $completed = 0;
        foreach ($fields as $field) {
            if (! empty($user->$field)) {
                $completed++;
            }
        }

        return (int) round(($completed / count($fields)) * 100);
    }

    protected function getRecommendedProviders($user)
    {
        $serviceTypes = [];

        $leadServiceTypes = Lead::where('user_id', $user->id)
            ->distinct()
            ->pluck('service_type')
            ->toArray();

        if (! empty($user->onboarding_data['services']['types'])) {
            $serviceTypes = array_merge($serviceTypes, $user->onboarding_data['services']['types']);
        }

        $serviceTypes = array_unique(array_merge($serviceTypes, $leadServiceTypes));

        $query = ServiceProvider::query()
            ->with(['user:id,first_name,last_name,avatar,city,state,country'])
            ->active()
            ->acceptingClients()
            ->verified()
            ->exceptOwnListing($user)
            ->whereUserCountry($user->country);

        if (! empty($serviceTypes)) {
            $query->where(function ($q) use ($serviceTypes) {
                foreach ($serviceTypes as $type) {
                    $q->orWhereJsonContains('service_types', $type);
                }
            });
        }

        if ($user->state) {
            $query->where(function ($q) use ($user) {
                $q->where('serves_remote', true)
                    ->orWhereHas('user', fn ($uq) => $uq->where('state', $user->state));
            });
        }

        return $query->orderByDesc('is_featured')
            ->orderByDesc('average_rating')
            ->limit(4)
            ->get();
    }
}
