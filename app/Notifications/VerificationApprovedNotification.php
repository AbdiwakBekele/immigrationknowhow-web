<?php

namespace App\Notifications;

use App\Models\IdentityVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerificationApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public IdentityVerification $verification
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your verification has been approved! ✓')
            ->greeting('Congratulations ' . $notifiable->first_name . '!')
            ->line('Your identity verification has been approved.')
            ->line('Your profile now displays a verification badge, which helps build trust with potential clients.')
            ->action('View Your Profile', url('/provider/profile'))
            ->line('Thank you for completing the verification process!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'verification_approved',
            'verification_id' => $this->verification->id,
            'message' => 'Your identity verification has been approved!',
        ];
    }
}
