<?php

return [
    /**
     * Free trial length for new provider subscriptions (Stripe + messaging).
     * iOS uses App Store Connect introductory offers (configure 6 months free there).
     */
    'provider_trial_months' => max(0, (int) env('PROVIDER_TRIAL_MONTHS', 6)),
];
