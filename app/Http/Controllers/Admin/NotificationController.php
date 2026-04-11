<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        if (! Schema::hasTable('notifications')) {
            return Inertia::render('Admin/Notifications/Index', [
                'notifications' => [
                    'data' => [],
                    'links' => [],
                    'prev_page_url' => null,
                    'next_page_url' => null,
                ],
                'notifications_table_missing' => true,
            ]);
        }

        $notifications = $request->user()
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

        return Inertia::render('Admin/Notifications/Index', [
            'notifications' => $notifications,
            'notifications_table_missing' => false,
        ]);
    }
}
