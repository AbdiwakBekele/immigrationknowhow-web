<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LibraryItem;
use App\Models\LibraryUserAccess;
use App\Models\Message;
use App\Models\ServiceProvider;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();

        // Calculate stats
        $stats = [
            'totalLeads' => Lead::where('user_id', $user->id)->count(),
            'unreadMessages' => $this->getUnreadMessageCount($user),
            'profileCompletion' => $this->calculateProfileCompletion($user),
        ];

        // Recent leads with provider info
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

        // Recent messages
        $recentMessages = Message::whereHas('conversation', function ($q) use ($user) {
            $q->forUser($user)->forServiceInquiries();
        })
            ->with(['sender:id,first_name,last_name,avatar', 'conversation:id,uuid'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($message) {
                return [
                    'uuid' => $message->uuid,
                    'conversation_uuid' => $message->conversation->uuid,
                    'sender' => $message->sender,
                    'body' => $message->body,
                    'created_at' => $message->created_at,
                ];
            });

        // Recommended providers based on user's interests
        $recommendedProviders = $this->getRecommendedProviders($user);

        // Featured library items
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

        // Buyer's recent library purchases (load full item so price/currency always serialize for the UI)
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
                    'purchased_at' => $access->purchased_at,
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

        return Inertia::render('User/Dashboard', [
            'stats' => $stats,
            'recentLeads' => $recentLeads,
            'recentMessages' => $recentMessages,
            'recommendedProviders' => $recommendedProviders,
            'libraryItems' => $libraryItems,
            'purchasedItems' => $purchasedItems,
        ]);
    }

    protected function getUnreadMessageCount($user): int
    {
        return Message::unreadIncomingCountFor($user);
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
        // Get user's service interests from their leads or onboarding data
        $serviceTypes = [];

        // From previous leads
        $leadServiceTypes = Lead::where('user_id', $user->id)
            ->distinct()
            ->pluck('service_type')
            ->toArray();

        // From onboarding data
        if (! empty($user->onboarding_data['services']['types'])) {
            $serviceTypes = array_merge($serviceTypes, $user->onboarding_data['services']['types']);
        }

        $serviceTypes = array_unique(array_merge($serviceTypes, $leadServiceTypes));

        $query = ServiceProvider::query()
            ->with(['user:id,first_name,last_name,avatar,city,state'])
            ->active()
            ->acceptingClients()
            ->verified();

        // If we have service preferences, prioritize matching providers
        if (! empty($serviceTypes)) {
            $query->where(function ($q) use ($serviceTypes) {
                foreach ($serviceTypes as $type) {
                    $q->orWhereJsonContains('service_types', $type);
                }
            });
        }

        // Prioritize by location if user has one
        if ($user->state) {
            $query->whereHas('user', function ($q) use ($user) {
                $q->where('state', $user->state)
                    ->orWhere('serves_remote', true);
            });
        }

        return $query->orderByDesc('is_featured')
            ->orderByDesc('average_rating')
            ->limit(4)
            ->get();
    }
}
