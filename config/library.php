<?php

return [
    /** Standard paid ebook/audiobook price in cents (display + Apple ebook credit). */
    'standard_price_cents' => (int) env('LIBRARY_EBOOK_PRICE_CENTS', 499),
    'currency' => strtoupper((string) env('LIBRARY_EBOOK_CURRENCY', 'USD')),
    /** Issue one free-ebook coupon on signup for these roles. */
    'signup_coupon_roles' => ['user', 'provider'],
];
