<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Notifications\RoleAwareTransactionalEmailNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class EmailTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        if (! Schema::hasTable('email_templates')) {
            return Inertia::render('Admin/Settings/EmailTemplates', [
                'templates' => [],
                'email_templates_table_missing' => true,
            ]);
        }

        return Inertia::render('Admin/Settings/EmailTemplates', [
            'templates' => EmailTemplate::query()
                ->orderBy('event_key')
                ->orderByRaw('role is null desc')
                ->orderBy('role')
                ->get()
                ->map(fn (EmailTemplate $template) => [
                    'id' => $template->id,
                    'title' => $template->name,
                    'event_key' => $template->event_key,
                    'event_label' => $this->eventLabel($template->event_key),
                    'role' => $template->role,
                    'role_label' => $this->roleLabel($template->role),
                    'name' => $template->name,
                    'subject' => $template->subject,
                    'is_active' => $template->is_active,
                ])->values(),
            'email_templates_table_missing' => false,
        ]);
    }

    public function show(Request $request, EmailTemplate $emailTemplate): Response
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $previewEmail = $request->user()?->email ?: config('mail.from.address') ?: '';
        $branding = \App\Models\PlatformSetting::branding();

        return Inertia::render('Admin/Settings/EmailTemplateShow', [
            'template' => [
                'id' => $emailTemplate->id,
                'title' => $emailTemplate->name,
                'name' => $emailTemplate->name,
                'subject' => $emailTemplate->subject,
                'body' => $emailTemplate->body,
                'action_label' => $emailTemplate->action_label,
                'action_url' => $emailTemplate->action_url,
                'is_active' => $emailTemplate->is_active,
                'event_key' => $emailTemplate->event_key,
                'event_label' => $this->eventLabel($emailTemplate->event_key),
                'role' => $emailTemplate->role,
                'role_label' => $this->roleLabel($emailTemplate->role),
            ],
            'supportedTokens' => [
                '{{first_name}}',
                '{{last_name}}',
                '{{full_name}}',
                '{{email}}',
                '{{company_name}}',
                '{{role}}',
                '{{role_label}}',
                '{{invite_link}}',
                '{{activation_link}}',
                '{{reset_link}}',
                '{{dashboard_link}}',
                '{{library_link}}',
                '{{coupon_code}}',
                '{{coupon}}',
                '{{support_email}}',
            ],
            'previewTokens' => [
                '{{first_name}}' => 'Amina',
                '{{last_name}}' => 'Bekele',
                '{{full_name}}' => 'Amina Bekele',
                '{{email}}' => $previewEmail,
                '{{company_name}}' => 'ImmigrationKnowHow',
                '{{role}}' => $emailTemplate->role ?? UserRole::USER->value,
                '{{role_label}}' => $this->roleLabel($emailTemplate->role),
                '{{invite_link}}' => 'https://example.com/invite/abc123',
                '{{activation_link}}' => 'https://example.com/activate/abc123',
                '{{reset_link}}' => 'https://example.com/reset/abc123',
                '{{dashboard_link}}' => 'https://example.com/dashboard',
                '{{library_link}}' => url('/library'),
                '{{coupon_code}}' => 'IKH-DEMO-CODE',
                '{{coupon}}' => 'IKH-DEMO-CODE',
                '{{support_email}}' => $branding['support_email'] ?? config('mail.from.address') ?? '',
            ],
        ]);
    }

    public function update(Request $request, EmailTemplate $emailTemplate): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'action_label' => ['nullable', 'string', 'max:255'],
            'action_url' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $emailTemplate->update([
            'name' => $validated['name'],
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'action_label' => $validated['action_label'] ?? null,
            'action_url' => $validated['action_url'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Email template updated.');
    }

    public function sendTest(Request $request, EmailTemplate $emailTemplate): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $validated = $request->validate([
            'to' => ['nullable', 'email', 'max:255'],
        ]);

        $to = $validated['to'] ?? ($request->user()?->email ?: config('mail.from.address'));

        if (! $to) {
            return back()->withErrors(['to' => 'No recipient email address available.']);
        }

        Notification::route('mail', $to)->notify(new RoleAwareTransactionalEmailNotification(
            $emailTemplate->event_key,
            $request->user(),
            [
                'role' => $emailTemplate->role,
                'invite_link' => url('/'),
                'activation_link' => url('/'),
                'reset_link' => url('/'),
                'dashboard_link' => url('/'),
                'library_link' => url('/library'),
                'coupon_code' => 'IKH-TEST-CODE',
                'coupon' => 'IKH-TEST-CODE',
            ],
        ));

        return back()->with('success', "Test email queued for delivery to {$to}.");
    }

    protected function roleLabel(?string $role): string
    {
        if (! $role) {
            return 'Default';
        }

        $matched = collect(UserRole::cases())->first(fn (UserRole $item) => $item->value === $role);

        return $matched?->label() ?? $role;
    }

    protected function eventLabel(string $eventKey): string
    {
        return match ($eventKey) {
            EmailTemplate::EVENT_INVITE => 'Invite emails',
            EmailTemplate::EVENT_WELCOME => 'Welcome emails',
            EmailTemplate::EVENT_ACCOUNT_ACTIVATION => 'Account activation emails',
            EmailTemplate::EVENT_EBOOK_COUPON => 'Free ebook coupon emails',
            default => $eventKey,
        };
    }
}
