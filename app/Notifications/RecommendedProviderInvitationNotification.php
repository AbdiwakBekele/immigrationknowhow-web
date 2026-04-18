<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RecommendedProviderInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public array $invite,
        public User $inviter,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $service = $this->invite['service_type'] ?? null;

        $message = (new MailMessage)
            ->subject($this->inviter->full_name.' invited you to join Immigrant Knowhow')
            ->greeting('Hello '.$this->invite['name'].'!')
            ->line($this->inviter->full_name.' recommended you as a trusted service provider for the Immigrant Knowhow community.');

        if ($service) {
            $message->line('Recommended service: '.$service);
        }

        return $message
            ->line('Create a provider profile so immigrants can find, message, and work with you from one place.')
            ->action('Create provider profile', route('register', ['role' => 'provider']))
            ->line('You can ignore this email if you were not expecting an invitation.');
    }
}
