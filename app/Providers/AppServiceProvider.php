<?php

namespace App\Providers;

use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Support\TransactionalEmailTemplateRenderer;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(!$this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(!$this->app->isProduction());

        VerifyEmail::toMailUsing(function (object $notifiable, string $url): MailMessage {
            $renderer = app(TransactionalEmailTemplateRenderer::class);
            $user = $notifiable instanceof User ? $notifiable : null;
            $payload = $renderer->render(EmailTemplate::EVENT_ACCOUNT_ACTIVATION, $user, [
                'activation_link' => $url,
            ]);

            return (new MailMessage)
                ->subject($payload['subject'])
                ->greeting('Hello '.($payload['tokens']['{{first_name}}'] ?: 'there').'!')
                ->line($payload['body'])
                ->action($payload['action_label'] ?: 'Verify Email Address', $payload['action_url'] ?: $url)
                ->line('If you did not create an account, no further action is required.');
        });

        Event::listen(MessageSent::class, function (MessageSent $event): void {
            if (! Schema::hasTable('email_logs')) {
                return;
            }

            $message = $event->message;

            if (! $message instanceof Email) {
                return;
            }

            $from = $message->getFrom();
            $to = $message->getTo();
            $cc = $message->getCc();
            $bcc = $message->getBcc();

            EmailLog::query()->create([
                'direction' => 'outgoing',
                'status' => 'sent',
                'subject' => $message->getSubject(),
                'from_email' => $this->firstEmail($from),
                'to_email' => $this->firstEmail($to),
                'cc' => $this->emailsToString($cc),
                'bcc' => $this->emailsToString($bcc),
                'message_id' => $message->getHeaders()->get('Message-ID')?->getBodyAsString(),
                'provider' => config('mail.default'),
                'payload' => [
                    'html' => $message->getHtmlBody(),
                    'text' => $message->getTextBody(),
                    'to' => $this->emailsToArray($to),
                    'from' => $this->emailsToArray($from),
                    'cc' => $this->emailsToArray($cc),
                    'bcc' => $this->emailsToArray($bcc),
                ],
                'sent_at' => now(),
            ]);
        });

        if (config('app.env') === 'production') {
            \URL::forceScheme('https');
        }
    }

    /**
     * @param  array<int,Address>  $addresses
     * @return array<int,string>
     */
    private function emailsToArray(array $addresses): array
    {
        return collect($addresses)
            ->map(fn (Address $address) => $address->getAddress())
            ->values()
            ->all();
    }

    /**
     * @param  array<int,Address>  $addresses
     */
    private function firstEmail(array $addresses): ?string
    {
        return $this->emailsToArray($addresses)[0] ?? null;
    }

    /**
     * @param  array<int,Address>  $addresses
     */
    private function emailsToString(array $addresses): ?string
    {
        $items = $this->emailsToArray($addresses);

        return empty($items) ? null : implode(', ', $items);
    }
}