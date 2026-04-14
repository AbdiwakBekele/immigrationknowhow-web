<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\Library\FulfillLibraryStripeCheckout;
use App\Actions\Video\FulfillVideoStripeCheckout;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Event;
use Stripe\Webhook;

class StripeLibraryWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        FulfillLibraryStripeCheckout $fulfillLibrary,
        FulfillVideoStripeCheckout $fulfillVideo
    ): Response
    {
        $secret = config('services.stripe.webhook_secret');
        if (! is_string($secret) || $secret === '') {
            return response('Webhook secret not configured', 503);
        }

        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature', '');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (UnexpectedValueException|\Throwable $e) {
            return response('Invalid payload or signature', 400);
        }

        if (! $event instanceof Event) {
            return response('Invalid event', 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            if ($session instanceof \Stripe\Checkout\Session) {
                $app = (string) ($session->metadata['app'] ?? '');
                if ($app === 'video') {
                    $fulfillVideo($session);
                } else {
                    $fulfillLibrary($session);
                }
            }
        }

        return response('OK', 200);
    }
}
