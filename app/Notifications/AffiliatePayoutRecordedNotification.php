<?php

namespace App\Notifications;

use App\Models\AffiliatePayout;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AffiliatePayoutRecordedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public AffiliatePayout $payout)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Affiliate payout recorded')
            ->greeting('Hello '.$notifiable->first_name.'!')
            ->line('A payout has been recorded for your affiliate account.')
            ->line('Amount: '.$this->payout->currency.' '.$this->payout->amount)
            ->line('Payment method: '.$this->payout->payment_method)
            ->action('View payout history', route('affiliate.payouts.index'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'affiliate_payout_recorded',
            'payout_id' => $this->payout->id,
            'amount' => $this->payout->amount,
            'currency' => $this->payout->currency,
        ];
    }
}
