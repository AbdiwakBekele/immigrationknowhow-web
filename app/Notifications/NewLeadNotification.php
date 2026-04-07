<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewLeadNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Lead $lead
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Lead: ' . $this->lead->service_type_label)
            ->greeting('Hello ' . $notifiable->first_name . '!')
            ->line('You have received a new lead from ' . $this->lead->user->full_name . '.')
            ->line('Service requested: ' . $this->lead->service_type_label)
            ->line('Message: ' . \Illuminate\Support\Str::limit($this->lead->message, 100))
            ->action('View Lead', route('provider.leads.show', $this->lead))
            ->line('Please respond promptly to increase your chances of conversion.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'lead_id' => $this->lead->id,
            'lead_uuid' => $this->lead->uuid,
            'user_name' => $this->lead->user->full_name,
            'service_type' => $this->lead->service_type_label,
            'message' => 'New lead from ' . $this->lead->user->full_name,
        ];
    }
}
