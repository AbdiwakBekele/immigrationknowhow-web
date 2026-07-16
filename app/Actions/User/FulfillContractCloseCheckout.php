<?php

namespace App\Actions\User;

use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;

/**
 * Placeholder fulfillment for contract-close Checkout sessions.
 * Safe no-op until contract-close Stripe metadata is fully wired.
 */
final class FulfillContractCloseCheckout
{
    public function __invoke(Session $session): bool
    {
        if (($session->metadata['app'] ?? '') !== 'contract_close') {
            return false;
        }

        Log::warning('stripe.contract_close.fulfillment_pending', [
            'checkout_session_id' => (string) $session->id,
            'payment_status' => (string) ($session->payment_status ?? ''),
        ]);

        return false;
    }
}
