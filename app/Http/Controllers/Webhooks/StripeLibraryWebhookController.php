<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\Advertiser\FulfillAdvertiserStripeCheckout;
use App\Actions\Affiliates\CreateAffiliateEarningAction;
use App\Actions\Library\FulfillLibraryStripeCheckout;
use App\Actions\Provider\FulfillProviderStripeCheckout;
use App\Actions\Provider\SyncProviderStripeSubscription;
use App\Actions\User\FulfillContractCloseCheckout;
use App\Actions\Video\FulfillVideoStripeCheckout;
use App\Enums\AffiliateCommissionTrigger;
use App\Http\Controllers\Controller;
use App\Models\AiAssistantSubscription;
use App\Models\ProviderSubscription;
use App\Models\ProviderSubscriptionPayment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Checkout\Session;
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
        FulfillContractCloseCheckout $fulfillContractClose,
    ): Response {
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
            if ($session instanceof Session) {
                $app = (string) ($session->metadata['app'] ?? '');
                if ($app === 'video') {
                    $fulfillVideo($session);
                } elseif ($app === 'advertiser_ad') {
                    $fulfillAdvertiser($session);
                } elseif ($app === 'provider_subscription') {
                    $fulfillProvider($session);
                } elseif ($app === 'ai_assistant') {
                    $this->syncAiAssistantCheckout($session);
                } elseif ($app === 'contract_close') {
                    $fulfillContractClose($session);
                } else {
                    $fulfillLibrary($session);
                }
            }
        } elseif (in_array($event->type, ['customer.subscription.created', 'customer.subscription.updated', 'customer.subscription.deleted'], true)) {
            $subscription = $event->data->object;
            if ($subscription instanceof Subscription) {
                $app = (string) ($subscription->metadata['app'] ?? '');
                if ($app === 'ai_assistant') {
                    $this->syncAiAssistantSubscription($subscription);
                } else {
                    $syncProviderSubscription($subscription);
                }
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

    private function syncAiAssistantCheckout(Session $session): void
    {
        $userId = (int) ($session->metadata['user_id'] ?? $session->client_reference_id ?? 0);
        if ($userId < 1) {
            return;
        }

        $user = User::query()->find($userId);
        if (! $user) {
            return;
        }

        AiAssistantSubscription::upsertForUser($user->id, [
            'stripe_customer_id' => is_string($session->customer) ? $session->customer : null,
            'stripe_subscription_id' => is_string($session->subscription) ? $session->subscription : null,
            'stripe_checkout_session_id' => (string) $session->id,
            'status' => 'active',
            'meta' => $session->toArray(),
        ]);
    }

    private function syncAiAssistantSubscription(Subscription $subscription): void
    {
        $userId = (int) ($subscription->metadata['user_id'] ?? 0);
        if ($userId < 1) {
            return;
        }

        $user = User::query()->find($userId);
        if (! $user) {
            return;
        }

        AiAssistantSubscription::upsertForUser($user->id, [
            'stripe_customer_id' => is_string($subscription->customer) ? $subscription->customer : null,
            'stripe_subscription_id' => (string) $subscription->id,
            'status' => (string) $subscription->status,
            'cancel_at_period_end' => (bool) $subscription->cancel_at_period_end,
            'current_period_end' => is_numeric($subscription->current_period_end)
                ? now()->setTimestamp((int) $subscription->current_period_end)
                : null,
            'canceled_at' => is_numeric($subscription->canceled_at)
                ? now()->setTimestamp((int) $subscription->canceled_at)
                : null,
            'meta' => $subscription->toArray(),
        ]);
    }
}
