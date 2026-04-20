<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class InboundEmailWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if (! Schema::hasTable('email_logs')) {
            return response()->json(['message' => 'Email logs table is not ready.'], 503);
        }

        $secret = (string) config('services.inbound_email.webhook_secret', '');
        if ($secret !== '') {
            $provided = (string) $request->header('X-Inbound-Email-Secret', '');
            if (! hash_equals($secret, $provided)) {
                return response()->json(['message' => 'Invalid webhook secret.'], 401);
            }
        }

        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'from' => ['required', 'string', 'max:255'],
            'to' => ['nullable', 'string', 'max:255'],
            'message_id' => ['nullable', 'string', 'max:255'],
            'provider' => ['nullable', 'string', 'max:255'],
            'text' => ['nullable', 'string'],
            'html' => ['nullable', 'string'],
            'payload' => ['nullable', 'array'],
        ]);

        EmailLog::query()->create([
            'direction' => 'incoming',
            'status' => 'received',
            'subject' => $validated['subject'] ?? null,
            'from_email' => $validated['from'],
            'to_email' => $validated['to'] ?? null,
            'message_id' => $validated['message_id'] ?? null,
            'provider' => $validated['provider'] ?? 'inbound-webhook',
            'payload' => array_filter([
                'text' => $validated['text'] ?? null,
                'html' => $validated['html'] ?? null,
                'raw' => $validated['payload'] ?? null,
            ], fn ($value) => $value !== null),
            'received_at' => now(),
        ]);

        return response()->json(['status' => 'ok']);
    }
}
