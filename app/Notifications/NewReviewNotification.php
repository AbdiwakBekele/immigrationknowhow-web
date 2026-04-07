<?php

namespace App\Notifications;

use App\Models\Review;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewReviewNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Review $review
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $stars = str_repeat('★', $this->review->overall_rating) . str_repeat('☆', 5 - $this->review->overall_rating);

        return (new MailMessage)
            ->subject('You received a new review!')
            ->greeting('Hello ' . $notifiable->first_name . '!')
            ->line('You just received a new ' . $this->review->overall_rating . '-star review.')
            ->line($stars)
            ->line('"' . \Str::limit($this->review->comment, 200) . '"')
            ->action('View & Respond', url('/provider/reviews'))
            ->line('Thank you for being a valued provider on ImmigrationKnowHow!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_review',
            'review_id' => $this->review->id,
            'rating' => $this->review->overall_rating,
            'reviewer_name' => $this->review->user?->full_name ?? 'A user',
            'message' => 'You received a new ' . $this->review->overall_rating . '-star review',
        ];
    }
}
