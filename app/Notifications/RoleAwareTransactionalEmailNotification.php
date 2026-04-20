<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\TransactionalEmailTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RoleAwareTransactionalEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $eventKey,
        protected ?User $user = null,
        protected array $context = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $renderer = app(TransactionalEmailTemplateRenderer::class);
        $payload = $renderer->render($this->eventKey, $this->user ?? ($notifiable instanceof User ? $notifiable : null), $this->context);

        $mail = (new MailMessage)
            ->subject($payload['subject'])
            ->greeting('Hello '.($payload['tokens']['{{first_name}}'] ?: 'there').'!')
            ->line($payload['body']);

        if (filled($payload['action_label']) && filled($payload['action_url'])) {
            $mail->action($payload['action_label'], $payload['action_url']);
        }

        return $mail;
    }
}
