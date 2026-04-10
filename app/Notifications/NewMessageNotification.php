<?php

namespace App\Notifications;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Message $message
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $sender = $this->message->sender;
        $conversation = $this->message->conversation;

        $conversationUrl = $notifiable->serviceProvider
            ? route('provider.messages.show', $conversation->uuid)
            : route('messages.show', $conversation->uuid);

        return (new MailMessage)
            ->subject("New message from {$sender->full_name}")
            ->greeting("Hello {$notifiable->first_name}!")
            ->line("{$sender->full_name} sent you a message:")
            ->line('"' . \Str::limit($this->message->body, 200) . '"')
            ->action('View Conversation', $conversationUrl)
            ->line('Reply to keep the conversation going!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_message',
            'message_id' => $this->message->id,
            'message_uuid' => $this->message->uuid,
            'conversation_uuid' => $this->message->conversation->uuid,
            'sender_id' => $this->message->sender_id,
            'sender_name' => $this->message->sender->full_name,
            'message_preview' => \Str::limit($this->message->body, 100),
        ];
    }
}
