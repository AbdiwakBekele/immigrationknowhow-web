<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

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
        ]);
    }

    public function show(Request $request, EmailTemplate $emailTemplate): Response
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

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
                '{{dashboard_link}}',
                '{{support_email}}',
            ],
            'previewTokens' => [
                '{{first_name}}' => 'Amina',
                '{{last_name}}' => 'Bekele',
                '{{full_name}}' => 'Amina Bekele',
                '{{email}}' => 'amina@example.com',
                '{{company_name}}' => 'ImmigrationKnowHow',
                '{{role}}' => $emailTemplate->role ?? UserRole::USER->value,
                '{{role_label}}' => $this->roleLabel($emailTemplate->role),
                '{{invite_link}}' => 'https://example.com/invite/abc123',
                '{{activation_link}}' => 'https://example.com/activate/abc123',
                '{{dashboard_link}}' => 'https://example.com/dashboard',
                '{{support_email}}' => 'support@example.com',
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
            default => $eventKey,
        };
    }
}
