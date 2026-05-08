<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class EmailVerificationNotificationController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return back()->with('error', 'You must be signed in to request email verification.');
        }

        if ($user->hasVerifiedEmail()) {
            return back()->with('success', 'Your email is already verified.');
        }

        $verificationLog = null;
        if (Schema::hasTable('email_logs')) {
            $verificationLog = EmailLog::query()->create([
                'direction' => 'outgoing',
                'status' => 'queued',
                'subject' => 'Email verification requested',
                'from_email' => config('mail.from.address'),
                'to_email' => $user->email,
                'provider' => config('mail.default'),
                'user_id' => $user->id,
                'template_id' => Schema::hasTable('email_templates')
                    ? EmailTemplate::query()->where('event_key', EmailTemplate::EVENT_ACCOUNT_ACTIVATION)->value('id')
                    : null,
                'payload' => [
                    'event_key' => EmailTemplate::EVENT_ACCOUNT_ACTIVATION,
                    'flow' => 'service_needer_verification',
                    'trigger' => 'banner_verify_button',
                    'route' => $request->path(),
                    'request_ip' => $request->ip(),
                    'user_agent' => (string) $request->userAgent(),
                    'requested_at' => now()->toIso8601String(),
                ],
            ]);
        }

        Log::channel('single')->info('User email verification requested.', [
            'user_id' => $user->id,
            'email' => $user->email,
            'route' => $request->path(),
            'ip' => $request->ip(),
            'mailer' => config('mail.default'),
            'mail_host' => config('mail.mailers.smtp.host'),
            'mail_port' => config('mail.mailers.smtp.port'),
            'mail_encryption' => config('mail.mailers.smtp.encryption'),
            'email_log_id' => $verificationLog?->id,
        ]);

        try {
            $user->sendEmailVerificationNotification();

            if ($verificationLog) {
                $verificationLog->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'payload' => array_merge($verificationLog->payload ?? [], [
                        'notification_dispatched' => true,
                        'dispatched_at' => now()->toIso8601String(),
                    ]),
                ]);
            }
        } catch (Throwable $exception) {
            Log::channel('single')->error('User email verification send failed.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'route' => $request->path(),
                'ip' => $request->ip(),
                'mailer' => config('mail.default'),
                'exception_class' => get_class($exception),
                'error' => $exception->getMessage(),
                'email_log_id' => $verificationLog?->id,
            ]);

            if ($verificationLog) {
                $verificationLog->update([
                    'status' => 'failed',
                    'sent_at' => now(),
                    'payload' => array_merge($verificationLog->payload ?? [], [
                        'notification_dispatched' => false,
                        'failed_at' => now()->toIso8601String(),
                        'exception_class' => get_class($exception),
                        'error' => $exception->getMessage(),
                    ]),
                ]);
            }

            return back()->with('error', 'We could not send the verification email. Please try again.');
        }

        return back()->with('success', 'Verification email sent. Please check your inbox.');
    }
}
