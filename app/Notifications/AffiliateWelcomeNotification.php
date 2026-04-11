<?php

namespace App\Notifications;

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
        return (new MailMessage)
            ->subject('Welcome to the affiliate program')
            ->greeting('Welcome '.$notifiable->first_name.'!')
            ->line('Your affiliate account is now ready to use.')
            ->action('Open affiliate dashboard', route('affiliate.dashboard'))
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
