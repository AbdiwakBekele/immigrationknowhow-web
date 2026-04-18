<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Manual library payments
    |--------------------------------------------------------------------------
    |
    | Stripe is the default payment path for library purchases. Manual payments
    | are opt-in for installs that intentionally support bank transfer, PayPal,
    | or another offline flow.
    |
    */
    'enabled' => filter_var(env('LIBRARY_MANUAL_PAYMENTS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    'instructions' => trim((string) env('MANUAL_PAYMENT_INSTRUCTIONS', '')),

    /*
    | Used when manual payments are enabled and MANUAL_PAYMENT_INSTRUCTIONS is empty.
    | Replace via .env for your real bank details or PayPal email.
    */
    'default_instructions' => <<<'TXT'
Send payment using the method you use with our team (for example bank transfer or PayPal).
Include your account email in the payment memo if the provider allows it.

After you pay, submit the form on the next screen with your payment reference
(for example transaction ID, confirmation number, or a short note). An administrator
will verify your payment and unlock your download — usually within one business day.
TXT,

];
