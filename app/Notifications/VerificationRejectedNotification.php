<?php

namespace App\Notifications;

use App\Models\IdentityVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerificationRejectedNotification extends Notification implements ShouldQueue
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
        $mail = (new MailMessage)
            ->subject('Action Required: Verification Update')
            ->greeting('Hello ' . $notifiable->first_name)
            ->line('We were unable to approve your recent identity verification submission.');

        if ($this->verification->rejection_reason) {
            $mail->line('**Reason:** ' . $this->verification->rejection_reason);
        }

        return $mail
            ->line('Please review the feedback and submit a new verification request with the required corrections.')
            ->action('Resubmit Verification', url('/provider/verification'))
            ->line('If you have questions, please contact our support team.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'verification_rejected',
            'verification_id' => $this->verification->id,
            'reason' => $this->verification->rejection_reason,
            'message' => 'Your verification was not approved. Please resubmit.',
        ];
    }
}
