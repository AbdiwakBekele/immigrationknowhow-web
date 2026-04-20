<?php

namespace App\Support;

use App\Models\EmailTemplate;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TransactionalEmailTemplateRenderer
{
    public function render(string $eventKey, ?User $user, array $context = []): array
    {
        $role = $this->resolveRole($user, $context['role'] ?? null);
        $template = $this->findTemplate($eventKey, $role);

        $branding = PlatformSetting::branding();
        $tokens = $this->tokens($user, $role, $context, $branding);

        $subject = $this->replaceTokens($template->subject, $tokens);
        $body = $this->replaceTokens($template->body, $tokens);
        $actionLabel = $template->action_label ? $this->replaceTokens($template->action_label, $tokens) : null;
        $actionUrl = $template->action_url ? $this->replaceTokens($template->action_url, $tokens) : null;

        return [
            'template_id' => $template->id,
            'name' => $template->name,
            'subject' => $subject,
            'body' => $body,
            'action_label' => $actionLabel,
            'action_url' => $actionUrl,
            'tokens' => $tokens,
            'event_key' => $eventKey,
            'role' => $role,
        ];
    }

    protected function findTemplate(string $eventKey, ?string $role): EmailTemplate
    {
        if (! Schema::hasTable('email_templates')) {
            return $this->defaultTemplate($eventKey);
        }

        $query = EmailTemplate::query()->where('event_key', $eventKey)->where('is_active', true);

        if ($role) {
            $template = (clone $query)->where('role', $role)->first();
            if ($template) {
                return $template;
            }
        }

        return $query->whereNull('role')->first() ?? $this->defaultTemplate($eventKey);
    }

    protected function tokens(?User $user, ?string $role, array $context, array $branding): array
    {
        return [
            '{{first_name}}' => (string) ($context['first_name'] ?? $user?->first_name ?? 'there'),
            '{{last_name}}' => (string) ($context['last_name'] ?? $user?->last_name ?? ''),
            '{{full_name}}' => trim((string) ($context['full_name'] ?? $user?->full_name ?? '')),
            '{{email}}' => (string) ($context['email'] ?? $user?->email ?? ''),
            '{{company_name}}' => (string) ($context['company_name'] ?? $branding['company_name'] ?? config('app.name')),
            '{{role}}' => (string) ($context['role'] ?? $role ?? 'user'),
            '{{role_label}}' => (string) ($context['role_label'] ?? $this->roleLabel($role)),
            '{{invite_link}}' => (string) ($context['invite_link'] ?? ''),
            '{{activation_link}}' => (string) ($context['activation_link'] ?? ''),
            '{{dashboard_link}}' => (string) ($context['dashboard_link'] ?? ''),
            '{{support_email}}' => (string) ($context['support_email'] ?? $branding['support_email'] ?? config('mail.from.address')),
        ];
    }

    protected function replaceTokens(string $value, array $tokens): string
    {
        return str_replace(array_keys($tokens), array_values($tokens), $value);
    }

    protected function resolveRole(?User $user, ?string $contextRole): ?string
    {
        if ($contextRole) {
            return $contextRole;
        }

        return $user?->roles()->pluck('name')->sort()->first();
    }

    protected function roleLabel(?string $role): string
    {
        if (! $role) {
            return 'User';
        }

        return (string) Str::of($role)->replace('_', ' ')->title();
    }

    protected function defaultTemplate(string $eventKey): EmailTemplate
    {
        return new EmailTemplate([
            'name' => 'Fallback template',
            'subject' => match ($eventKey) {
                EmailTemplate::EVENT_INVITE => 'You are invited to {{company_name}}',
                EmailTemplate::EVENT_ACCOUNT_ACTIVATION => 'Activate your {{company_name}} account',
                default => 'Welcome to {{company_name}}',
            },
            'body' => match ($eventKey) {
                EmailTemplate::EVENT_INVITE => 'Hi {{first_name}}, you have been invited to join {{company_name}}.',
                EmailTemplate::EVENT_ACCOUNT_ACTIVATION => 'Please verify your email address to activate your account.',
                default => 'Welcome {{first_name}}. Your account is ready.',
            },
            'action_label' => match ($eventKey) {
                EmailTemplate::EVENT_INVITE => 'Open invite',
                EmailTemplate::EVENT_ACCOUNT_ACTIVATION => 'Verify email',
                default => 'Open dashboard',
            },
            'action_url' => match ($eventKey) {
                EmailTemplate::EVENT_INVITE => '{{invite_link}}',
                EmailTemplate::EVENT_ACCOUNT_ACTIVATION => '{{activation_link}}',
                default => '{{dashboard_link}}',
            },
            'event_key' => $eventKey,
            'role' => null,
            'is_active' => true,
        ]);
    }
}
