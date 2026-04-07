<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\LibraryItem;
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
                $q->where('user_id', $user->id);
            })
            ->where('sender_id', '!=', $user->id)
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
            ->limit(3)
            ->get(['uuid', 'slug', 'title', 'author', 'type', 'cover_image']);

        return Inertia::render('User/Dashboard', [
            'stats' => $stats,
            'recentLeads' => $recentLeads,
            'recentMessages' => $recentMessages,
            'recommendedProviders' => $recommendedProviders,
            'libraryItems' => $libraryItems,
        ]);
    }

    protected function getUnreadMessageCount($user): int
    {
        return Conversation::where('user_id', $user->id)
            ->whereHas('messages', function ($q) use ($user) {
                $q->where('sender_id', '!=', $user->id)
                    ->whereDoesntHave('reads', function ($rq) use ($user) {
                        $rq->where('user_id', $user->id);
                    });
            })
            ->count();
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
            if (!empty($user->$field)) {
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
        if (!empty($user->onboarding_data['services']['types'])) {
            $serviceTypes = array_merge($serviceTypes, $user->onboarding_data['services']['types']);
        }
        
        $serviceTypes = array_unique(array_merge($serviceTypes, $leadServiceTypes));

        $query = ServiceProvider::query()
            ->with(['user:id,first_name,last_name,avatar,city,state'])
            ->active()
            ->acceptingClients()
            ->verified();

        // If we have service preferences, prioritize matching providers
        if (!empty($serviceTypes)) {
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
