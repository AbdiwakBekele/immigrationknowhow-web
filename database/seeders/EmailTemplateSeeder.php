<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->defaults() as $template) {
            EmailTemplate::query()->updateOrCreate(
                [
                    'event_key' => $template['event_key'],
                    'role' => $template['role'],
                ],
                $template
            );
        }
    }

    protected function defaults(): array
    {
        $templates = [];

        $templates[] = [
            'event_key' => EmailTemplate::EVENT_INVITE,
            'role' => null,
            'name' => 'Default invite',
            'subject' => 'You are invited to {{company_name}}',
            'body' => 'Hi {{first_name}}, you have been invited as a {{role_label}}. Click the button to continue.',
            'action_label' => 'Open invite',
            'action_url' => '{{invite_link}}',
            'is_active' => true,
        ];

        $templates[] = [
            'event_key' => EmailTemplate::EVENT_WELCOME,
            'role' => null,
            'name' => 'Default welcome',
            'subject' => 'Welcome to {{company_name}}',
            'body' => 'Welcome {{first_name}}! Your {{role_label}} account is ready.',
            'action_label' => 'Go to dashboard',
            'action_url' => '{{dashboard_link}}',
            'is_active' => true,
        ];

        $templates[] = [
            'event_key' => EmailTemplate::EVENT_ACCOUNT_ACTIVATION,
            'role' => null,
            'name' => 'Default account activation',
            'subject' => 'Activate your {{company_name}} account',
            'body' => 'Please confirm your email to activate your account and secure access.',
            'action_label' => 'Activate account',
            'action_url' => '{{activation_link}}',
            'is_active' => true,
        ];

        foreach (UserRole::cases() as $role) {
            $templates[] = [
                'event_key' => EmailTemplate::EVENT_INVITE,
                'role' => $role->value,
                'name' => ucfirst($role->value).' invite',
                'subject' => '{{company_name}} invite for {{role_label}}',
                'body' => 'Hi {{first_name}}, you were invited to join as {{role_label}}.',
                'action_label' => 'Accept invite',
                'action_url' => '{{invite_link}}',
                'is_active' => true,
            ];

            $templates[] = [
                'event_key' => EmailTemplate::EVENT_WELCOME,
                'role' => $role->value,
                'name' => ucfirst($role->value).' welcome',
                'subject' => 'Welcome {{first_name}} - {{role_label}} account',
                'body' => 'Your {{role_label}} profile is live. You can continue in your dashboard.',
                'action_label' => 'Open dashboard',
                'action_url' => '{{dashboard_link}}',
                'is_active' => true,
            ];

            $templates[] = [
                'event_key' => EmailTemplate::EVENT_ACCOUNT_ACTIVATION,
                'role' => $role->value,
                'name' => ucfirst($role->value).' activation',
                'subject' => 'Activate your {{role_label}} account',
                'body' => 'Confirm your email to activate your {{role_label}} access on {{company_name}}.',
                'action_label' => 'Verify email',
                'action_url' => '{{activation_link}}',
                'is_active' => true,
            ];
        }

        return $templates;
    }
}
