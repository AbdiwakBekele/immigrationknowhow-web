<?php

namespace App\Providers;

use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Support\TransactionalEmailTemplateRenderer;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

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

        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            return route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
        });

        ResetPassword::toMailUsing(function (object $notifiable, string $token): MailMessage {
            $url = route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);

            $renderer = app(TransactionalEmailTemplateRenderer::class);
            $user = $notifiable instanceof User ? $notifiable : null;
            $payload = $renderer->render(EmailTemplate::EVENT_PASSWORD_RESET, $user, [
                'reset_link' => $url,
            ]);

            return (new MailMessage)
                ->subject($payload['subject'])
                ->greeting('Hello '.($payload['tokens']['{{first_name}}'] ?: 'there').'!')
                ->line($payload['body'])
                ->action($payload['action_label'] ?: 'Reset password', $payload['action_url'] ?: $url)
                ->line('If you did not request a password reset, no further action is required.')
                ->line('This link will expire in '.config('auth.passwords.'.config('auth.defaults.passwords').'.expire').' minutes.')
                ->withSymfonyMessage(function (Email $message) use ($payload, $user): void {
                    $headers = $message->getHeaders();
                    $headers->addTextHeader('X-IKH-Event-Key', EmailTemplate::EVENT_PASSWORD_RESET);

                    if (! empty($payload['template_id'])) {
                        $headers->addTextHeader('X-IKH-Template-ID', (string) $payload['template_id']);
                    }

                    if ($user?->id) {
                        $headers->addTextHeader('X-IKH-User-ID', (string) $user->id);
                    }
                });
        });

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
                ->line('If you did not create an account, no further action is required.')
                ->withSymfonyMessage(function (Email $message) use ($payload, $user): void {
                    $headers = $message->getHeaders();
                    $headers->addTextHeader('X-IKH-Event-Key', EmailTemplate::EVENT_ACCOUNT_ACTIVATION);

                    if (! empty($payload['template_id'])) {
                        $headers->addTextHeader('X-IKH-Template-ID', (string) $payload['template_id']);
                    }

                    if ($user?->id) {
                        $headers->addTextHeader('X-IKH-User-ID', (string) $user->id);
                    }
                });
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

            try {
                $payload = [
                    'html' => $message->getHtmlBody(),
                    'text' => $message->getTextBody(),
                    'event_key' => $this->headerValue($message, 'X-IKH-Event-Key'),
                    'to' => $this->emailsToArray($to),
                    'from' => $this->emailsToArray($from),
                    'cc' => $this->emailsToArray($cc),
                    'bcc' => $this->emailsToArray($bcc),
                ];

                $queuedLog = EmailLog::query()
                    ->where('direction', 'outgoing')
                    ->where('status', 'queued')
                    ->where('subject', $message->getSubject())
                    ->where('to_email', $this->firstEmail($to))
                    ->latest('id')
                    ->first();

                if ($queuedLog) {
                    $queuedPayload = is_array($queuedLog->payload) ? $queuedLog->payload : [];

                    $queuedLog->update([
                        'status' => 'sent',
                        'message_id' => $message->getHeaders()->get('Message-ID')?->getBodyAsString(),
                        'provider' => config('mail.default'),
                        'user_id' => $this->headerInt($message, 'X-IKH-User-ID'),
                        'template_id' => $this->headerInt($message, 'X-IKH-Template-ID'),
                        'payload' => array_merge($queuedPayload, $payload),
                        'sent_at' => now(),
                    ]);

                    return;
                }

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
                    'user_id' => $this->headerInt($message, 'X-IKH-User-ID'),
                    'template_id' => $this->headerInt($message, 'X-IKH-Template-ID'),
                    'payload' => $payload,
                    'sent_at' => now(),
                ]);
            } catch (Throwable $exception) {
                report($exception);
            }
        });

        Event::listen(MessageSending::class, function (MessageSending $event): void {
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

            try {
                $alreadyLogged = EmailLog::query()
                    ->where('direction', 'outgoing')
                    ->where('status', 'queued')
                    ->where('subject', $message->getSubject())
                    ->where('to_email', $this->firstEmail($to))
                    ->latest('id')
                    ->first();

                if ($alreadyLogged) {
                    return;
                }

                EmailLog::query()->create([
                    'direction' => 'outgoing',
                    'status' => 'queued',
                    'subject' => $message->getSubject(),
                    'from_email' => $this->firstEmail($from),
                    'to_email' => $this->firstEmail($to),
                    'cc' => $this->emailsToString($cc),
                    'bcc' => $this->emailsToString($bcc),
                    'provider' => config('mail.default'),
                    'user_id' => $this->headerInt($message, 'X-IKH-User-ID'),
                    'template_id' => $this->headerInt($message, 'X-IKH-Template-ID'),
                    'payload' => [
                        'event_key' => $this->headerValue($message, 'X-IKH-Event-Key'),
                        'to' => $this->emailsToArray($to),
                        'from' => $this->emailsToArray($from),
                        'cc' => $this->emailsToArray($cc),
                        'bcc' => $this->emailsToArray($bcc),
                    ],
                ]);
            } catch (Throwable $exception) {
                report($exception);
            }
        });

        Event::listen(NotificationFailed::class, function (NotificationFailed $event): void {
            if ($event->channel !== 'mail' || ! Schema::hasTable('email_logs')) {
                return;
            }

            $toEmail = $this->notifiableEmail($event->notifiable);
            $userId = $event->notifiable instanceof User ? $event->notifiable->id : null;
            $exceptionMessage = null;
            if (isset($event->data['exception']) && $event->data['exception'] instanceof Throwable) {
                $exceptionMessage = $event->data['exception']->getMessage();
            }

            try {
                EmailLog::query()->create([
                    'direction' => 'outgoing',
                    'status' => 'failed',
                    'subject' => $event->data['subject'] ?? 'Email delivery failed',
                    'from_email' => config('mail.from.address'),
                    'to_email' => $toEmail,
                    'provider' => config('mail.default'),
                    'user_id' => $userId,
                    'payload' => [
                        'notification' => get_class($event->notification),
                        'channel' => $event->channel,
                        'error' => $exceptionMessage,
                        'data' => $this->normalizePayload($event->data),
                    ],
                    'sent_at' => now(),
                ]);
            } catch (Throwable $exception) {
                report($exception);
            }
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

    private function headerValue(Email $message, string $name): ?string
    {
        $value = $message->getHeaders()->get($name)?->getBodyAsString();

        return blank($value) ? null : $value;
    }

    private function headerInt(Email $message, string $name): ?int
    {
        $value = $this->headerValue($message, $name);
        if ($value === null || ! ctype_digit($value)) {
            return null;
        }

        return (int) $value;
    }

    private function notifiableEmail(mixed $notifiable): ?string
    {
        if (! is_object($notifiable)) {
            return null;
        }

        if (isset($notifiable->email) && is_string($notifiable->email) && $notifiable->email !== '') {
            return $notifiable->email;
        }

        if (method_exists($notifiable, 'routeNotificationFor')) {
            $route = $notifiable->routeNotificationFor('mail');
            if (is_string($route) && $route !== '') {
                return $route;
            }

            if (is_array($route) && isset($route[0]) && is_string($route[0])) {
                return $route[0];
            }
        }

        return null;
    }

    private function normalizePayload(mixed $value): mixed
    {
        if (is_null($value) || is_string($value) || is_int($value) || is_float($value) || is_bool($value)) {
            return $value;
        }

        if (is_array($value)) {
            return collect($value)
                ->map(fn (mixed $item) => $this->normalizePayload($item))
                ->all();
        }

        if ($value instanceof Throwable) {
            return [
                'class' => get_class($value),
                'message' => $value->getMessage(),
            ];
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        if (is_object($value)) {
            return ['class' => get_class($value)];
        }

        return (string) $value;
    }
}