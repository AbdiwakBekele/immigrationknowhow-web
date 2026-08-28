<?php

return [
    /** Standard paid ebook/audiobook price in cents (display + Apple ebook credit). */
    'standard_price_cents' => (int) env('LIBRARY_EBOOK_PRICE_CENTS', 599),
    'currency' => strtoupper((string) env('LIBRARY_EBOOK_CURRENCY', 'USD')),
    /** Issue one free-ebook coupon on signup for these roles. */
    'signup_coupon_roles' => ['user', 'provider'],

    'share_campaign' => [
        'required_shares' => (int) env('LIBRARY_SHARE_REQUIRED_COUNT', 5),
        'eligible_roles' => ['user', 'provider'],
        'shareable_types' => ['ebook'],
        /** When true, each user can earn the share reward only once. */
        'one_per_user' => (bool) env('LIBRARY_SHARE_ONE_PER_USER', true),
        /** Confirm a share as soon as the user opens a social share dialog. */
        'auto_confirm_on_intent' => (bool) env('LIBRARY_SHARE_AUTO_CONFIRM_INTENT', true),
        /** When auto_confirm_on_intent is false, require a click from Facebook/X referrer. */
        'require_social_referrer' => (bool) env('LIBRARY_SHARE_REQUIRE_SOCIAL_REFERRER', false),
        'intent_rate_limit_per_hour' => (int) env('LIBRARY_SHARE_INTENT_RATE_LIMIT', 10),
        'confirmation_window_hours' => (int) env('LIBRARY_SHARE_CONFIRMATION_WINDOW_HOURS', 48),
    ],
];
