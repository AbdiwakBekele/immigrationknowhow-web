<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $settings = PlatformSetting::current();

        return Inertia::render('Admin/Settings/Index', [
            'settings' => [
                'company_name' => $settings->company_name,
                'support_email' => $settings->support_email,
                'support_phone' => $settings->support_phone,
                'support_address' => $settings->support_address,
                'site_logo_url' => $settings->site_logo_url,
                'admin_logo_url' => $settings->admin_logo_url,
                'site_tagline' => $settings->site_tagline,
                'footer_tagline' => $settings->footer_tagline,
                'maintenance_mode' => $settings->maintenance_mode,
                'reviews_auto_approve' => $settings->reviews_auto_approve,
                'email_notifications' => $settings->email_notifications,
                'new_provider_alerts' => $settings->new_provider_alerts,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:50'],
            'support_address' => ['nullable', 'string', 'max:255'],
            'site_tagline' => ['nullable', 'string', 'max:255'],
            'footer_tagline' => ['nullable', 'string', 'max:1000'],
            'maintenance_mode' => ['nullable', 'boolean'],
            'reviews_auto_approve' => ['nullable', 'boolean'],
            'email_notifications' => ['nullable', 'boolean'],
            'new_provider_alerts' => ['nullable', 'boolean'],
            'site_logo' => ['nullable', 'file', 'mimes:svg,png,jpg,jpeg,webp,gif', 'max:2048'],
            'admin_logo' => ['nullable', 'file', 'mimes:svg,png,jpg,jpeg,webp,gif', 'max:2048'],
            'remove_site_logo' => ['nullable', 'boolean'],
            'remove_admin_logo' => ['nullable', 'boolean'],
        ]);

        $settings = PlatformSetting::current();

        $payload = [
            'company_name' => $validated['company_name'],
            'support_email' => $validated['support_email'] ?? null,
            'support_phone' => $validated['support_phone'] ?? null,
            'support_address' => $validated['support_address'] ?? null,
            'site_tagline' => $validated['site_tagline'] ?? null,
            'footer_tagline' => $validated['footer_tagline'] ?? null,
            'maintenance_mode' => $request->boolean('maintenance_mode'),
            'reviews_auto_approve' => $request->boolean('reviews_auto_approve'),
            'email_notifications' => $request->boolean('email_notifications', true),
            'new_provider_alerts' => $request->boolean('new_provider_alerts', true),
        ];

        if ($request->boolean('remove_site_logo') && $settings->site_logo_path) {
            Storage::disk('public')->delete($settings->site_logo_path);
            $payload['site_logo_path'] = null;
        }

        if ($request->boolean('remove_admin_logo') && $settings->admin_logo_path) {
            Storage::disk('public')->delete($settings->admin_logo_path);
            $payload['admin_logo_path'] = null;
        }

        if ($request->hasFile('site_logo')) {
            if ($settings->site_logo_path) {
                Storage::disk('public')->delete($settings->site_logo_path);
            }

            $payload['site_logo_path'] = $request->file('site_logo')->store('branding/site', 'public');
        }

        if ($request->hasFile('admin_logo')) {
            if ($settings->admin_logo_path) {
                Storage::disk('public')->delete($settings->admin_logo_path);
            }

            $payload['admin_logo_path'] = $request->file('admin_logo')->store('branding/admin', 'public');
        }

        $settings->update($payload);

        return back()->with('success', 'Settings saved successfully.');
    }
}
