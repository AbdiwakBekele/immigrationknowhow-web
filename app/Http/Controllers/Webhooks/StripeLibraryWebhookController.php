<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\Affiliates\CreateAffiliateEarningAction;
use App\Actions\Advertiser\FulfillAdvertiserStripeCheckout;
use App\Actions\Provider\FulfillProviderStripeCheckout;
use App\Actions\Provider\SyncProviderStripeSubscription;
use App\Enums\AffiliateCommissionTrigger;
use App\Actions\Library\FulfillLibraryStripeCheckout;
use App\Actions\Video\FulfillVideoStripeCheckout;
use App\Http\Controllers\Controller;
use App\Models\ProviderSubscription;
use App\Models\ProviderSubscriptionPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Event;
use Stripe\Invoice;
use Stripe\Subscription;
use Stripe\Webhook;

class StripeLibraryWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        FulfillLibraryStripeCheckout $fulfillLibrary,
        FulfillVideoStripeCheckout $fulfillVideo,
        FulfillAdvertiserStripeCheckout $fulfillAdvertiser,
        FulfillProviderStripeCheckout $fulfillProvider,
        SyncProviderStripeSubscription $syncProviderSubscription,
        CreateAffiliateEarningAction $createAffiliateEarning,
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
                } elseif ($app === 'advertiser_ad') {
                    $fulfillAdvertiser($session);
                } elseif ($app === 'provider_subscription') {
                    $fulfillProvider($session);
                } else {
                    $fulfillLibrary($session);
                }
            }
        } elseif (in_array($event->type, ['customer.subscription.created', 'customer.subscription.updated', 'customer.subscription.deleted'], true)) {
            $subscription = $event->data->object;
            if ($subscription instanceof Subscription) {
                $syncProviderSubscription($subscription);
            }
        } elseif (in_array($event->type, ['invoice.paid', 'invoice.payment_failed'], true)) {
            $invoice = $event->data->object;
            if ($invoice instanceof Invoice) {
                $payment = $this->syncInvoice($invoice);
                if ($payment && $event->type === 'invoice.paid') {
                    $this->createCommission($payment, $createAffiliateEarning);
                }
            }
        }

        return response('OK', 200);
    }

    private function syncInvoice(Invoice $invoice): ?ProviderSubscriptionPayment
    {
        $stripeSubscriptionId = is_string($invoice->subscription) ? $invoice->subscription : null;
        if (! $stripeSubscriptionId) {
            return null;
        }

        $providerSubscription = ProviderSubscription::query()
            ->where('stripe_subscription_id', $stripeSubscriptionId)
            ->with(['provider', 'plan'])
            ->first();

        if (! $providerSubscription) {
            return null;
        }

        $status = (string) $invoice->status;
        $localStatus = match ($status) {
            'paid' => 'paid',
            'open' => 'open',
            'void' => 'void',
            'uncollectible' => 'failed',
            default => 'open',
        };

        return ProviderSubscriptionPayment::query()->updateOrCreate(
            ['stripe_invoice_id' => (string) $invoice->id],
            [
                'provider_subscription_id' => $providerSubscription->id,
                'service_provider_id' => $providerSubscription->service_provider_id,
                'subscription_plan_id' => $providerSubscription->subscription_plan_id,
                'stripe_payment_intent_id' => is_string($invoice->payment_intent) ? $invoice->payment_intent : null,
                'amount_due_cents' => (int) ($invoice->amount_due ?? 0),
                'amount_paid_cents' => (int) ($invoice->amount_paid ?? 0),
                'currency' => strtoupper((string) ($invoice->currency ?? 'USD')),
                'status' => $localStatus,
                'billing_reason' => (string) ($invoice->billing_reason ?? ''),
                'paid_at' => isset($invoice->status_transitions->paid_at) && is_numeric($invoice->status_transitions->paid_at)
                    ? now()->setTimestamp((int) $invoice->status_transitions->paid_at)
                    : null,
                'invoice_pdf_url' => is_string($invoice->invoice_pdf) ? $invoice->invoice_pdf : null,
                'raw_payload' => $invoice->toArray(),
            ]
        );
    }

    private function createCommission(ProviderSubscriptionPayment $payment, CreateAffiliateEarningAction $createAffiliateEarning): void
    {
        $providerSubscription = $payment->providerSubscription()->with(['affiliateReferral.referredUser'])->first();
        if (! $providerSubscription || ! $providerSubscription->affiliateReferral) {
            return;
        }

        $trigger = $payment->billing_reason === 'subscription_create'
            ? AffiliateCommissionTrigger::PROVIDER_SUBSCRIPTION_INITIAL
            : AffiliateCommissionTrigger::PROVIDER_SUBSCRIPTION_RECURRING;

        $createAffiliateEarning->handle(
            $providerSubscription->affiliateReferral,
            $trigger,
            ProviderSubscriptionPayment::class,
            $payment->id,
            ((float) $payment->amount_paid_cents) / 100,
            'Provider subscription commission generated from Stripe invoice.',
            [
                'provider_subscription_id' => $providerSubscription->id,
                'stripe_invoice_id' => $payment->stripe_invoice_id,
            ]
        );
    }
}
