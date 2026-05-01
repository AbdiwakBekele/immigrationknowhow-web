<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ProviderNotificationsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! Schema::hasTable('notifications')) {
            return response()->json([
                'success' => true,
                'message' => 'OK',
                'data' => [
                    'notifications' => [
                        'data' => [],
                        'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => 0],
                    ],
                    'notifications_table_missing' => true,
                ],
            ]);
        }

        $paginator = $request->user()
            ->notifications()
            ->latest()
            ->paginate(20)
            ->through(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'data' => $notification->data,
                    'read_at' => $notification->read_at?->toIso8601String(),
                    'created_at' => $notification->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'notifications' => [
                    'data' => $paginator->items(),
                    'meta' => [
                        'current_page' => $paginator->currentPage(),
                        'last_page' => $paginator->lastPage(),
                        'per_page' => $paginator->perPage(),
                        'total' => $paginator->total(),
                    ],
                ],
                'notifications_table_missing' => false,
            ],
        ]);
    }

    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        if (! Schema::hasTable('notifications')) {
            return response()->json(['success' => true, 'message' => 'OK', 'data' => (object) []]);
        }

        $request->user()
            ->unreadNotifications()
            ->where('id', $notification)
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => (object) [],
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        if (! Schema::hasTable('notifications')) {
            return response()->json(['success' => true, 'message' => 'OK', 'data' => (object) []]);
        }

        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => (object) [],
        ]);
    }
}
