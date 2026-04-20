<?php

namespace App\Notifications;

use App\Models\EmailTemplate;
use App\Models\AffiliateInvite;
use App\Support\TransactionalEmailTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AffiliateInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public AffiliateInvite $invite,
        public string $token,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inviteLink = route('affiliate.invites.show', ['token' => $this->token]);
        $renderer = app(TransactionalEmailTemplateRenderer::class);
        $payload = $renderer->render(EmailTemplate::EVENT_INVITE, null, [
            'role' => 'affiliate',
            'first_name' => $this->invite->name,
            'full_name' => $this->invite->name,
            'email' => $this->invite->email,
            'invite_link' => $inviteLink,
            'activation_link' => $inviteLink,
        ]);

        return (new MailMessage)
            ->subject($payload['subject'])
            ->greeting('Hello '.$this->invite->name.'!')
            ->line($payload['body'])
            ->action($payload['action_label'] ?: 'Accept invitation', $payload['action_url'] ?: $inviteLink)
            ->line('This invitation link expires in 7 days.');
    }
}
