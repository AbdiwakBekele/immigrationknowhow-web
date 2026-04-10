<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Settings/Index', [
            'settings' => [
                'maintenance_mode' => false,
                'reviews_auto_approve' => false,
                'email_notifications' => true,
                'new_provider_alerts' => true,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'maintenance_mode' => ['nullable', 'boolean'],
            'reviews_auto_approve' => ['nullable', 'boolean'],
            'email_notifications' => ['nullable', 'boolean'],
            'new_provider_alerts' => ['nullable', 'boolean'],
        ]);

        return back()->with('success', 'Settings saved successfully.');
    }
}
