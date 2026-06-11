<?php

return [
    'default_price_cents' => (int) env('AD_POST_PRICE_CENTS', 999),
    /** Number of ads each user can publish without payment (lifetime, per account). */
    'free_limit' => max(0, (int) env('AD_FREE_LIMIT', 3)),
    'currency' => strtoupper((string) env('AD_POST_CURRENCY', 'USD')),
    /**
     * When true, paid and free ads are not publicly visible until an admin approves them.
     * Set ADS_REQUIRE_ADMIN_APPROVAL=false to auto-publish after payment (legacy behavior).
     */
    'require_admin_approval' => filter_var(env('ADS_REQUIRE_ADMIN_APPROVAL', true), FILTER_VALIDATE_BOOLEAN),
];
