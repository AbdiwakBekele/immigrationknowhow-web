<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Manual library payments (when Stripe is not configured)
    |--------------------------------------------------------------------------
    |
    | Shown on the library pay page. Use plain text or multiple lines; the UI
    | preserves line breaks. Example: bank name, account number, PayPal email,
    | and what reference to use.
    |
    */
    'instructions' => trim((string) env('MANUAL_PAYMENT_INSTRUCTIONS', '')),

    /*
    | Used when MANUAL_PAYMENT_INSTRUCTIONS is empty (e.g. fresh .env).
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
