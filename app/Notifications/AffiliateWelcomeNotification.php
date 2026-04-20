<?php

namespace App\Notifications;

use App\Models\EmailTemplate;
use App\Support\TransactionalEmailTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AffiliateWelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $dashboardLink = route('affiliate.dashboard');
        $renderer = app(TransactionalEmailTemplateRenderer::class);
        $payload = $renderer->render(EmailTemplate::EVENT_WELCOME, $notifiable, [
            'role' => 'affiliate',
            'dashboard_link' => $dashboardLink,
            'activation_link' => $dashboardLink,
        ]);

        return (new MailMessage)
            ->subject($payload['subject'])
            ->greeting('Welcome '.$notifiable->first_name.'!')
            ->line($payload['body'])
            ->action($payload['action_label'] ?: 'Open affiliate dashboard', $payload['action_url'] ?: $dashboardLink)
            ->line('Thanks for partnering with ImmigrationKnowHow.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'affiliate_welcome',
            'message' => 'Your affiliate account is active.',
        ];
    }
}
