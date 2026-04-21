<?php

namespace App\Notifications;

use App\Models\EmailTemplate;
use App\Support\TransactionalEmailTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

class AffiliateWelcomeNotification extends Notification
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
            ->line('Thanks for partnering with ImmigrationKnowHow.')
            ->withSymfonyMessage(function (Email $message) use ($payload, $notifiable): void {
                $headers = $message->getHeaders();
                $headers->addTextHeader('X-IKH-Event-Key', EmailTemplate::EVENT_WELCOME);

                if (! empty($payload['template_id'])) {
                    $headers->addTextHeader('X-IKH-Template-ID', (string) $payload['template_id']);
                }

                if (isset($notifiable->id)) {
                    $headers->addTextHeader('X-IKH-User-ID', (string) $notifiable->id);
                }
            });
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'affiliate_welcome',
            'message' => 'Your affiliate account is active.',
        ];
    }
}
