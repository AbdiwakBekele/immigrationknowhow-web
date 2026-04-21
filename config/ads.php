<?php

return [
    'default_price_cents' => (int) env('AD_POST_PRICE_CENTS', 2500),
    'currency' => strtoupper((string) env('AD_POST_CURRENCY', 'USD')),
];

