<?php

namespace App\Notifications;

use App\Models\AffiliateInvite;
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
        return (new MailMessage)
            ->subject('You have been invited to join the affiliate program')
            ->greeting('Hello '.$this->invite->name.'!')
            ->line('You have been invited to join the ImmigrationKnowHow affiliate program.')
            ->line('Complete your setup to verify your email, access your referral dashboard, and track earnings.')
            ->action('Accept invitation', route('affiliate.invites.show', ['token' => $this->token]))
            ->line('This invitation link expires in 7 days.');
    }
}
