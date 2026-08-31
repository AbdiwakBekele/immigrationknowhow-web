<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin two-factor authentication
    |--------------------------------------------------------------------------
    |
    | When enabled, admin and super_admin accounts must verify an SMS code
    | via Twilio Verify after password login before accessing /admin routes.
    |
    */
    'enabled' => env('ADMIN_2FA_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Verification session lifetime (hours)
    |--------------------------------------------------------------------------
    |
    | After a successful OTP, admins remain verified for this many hours within
    | the same authenticated session. A fresh password login always requires OTP.
    |
    */
    'session_ttl_hours' => (int) env('ADMIN_2FA_SESSION_TTL_HOURS', 12),

    /*
    |--------------------------------------------------------------------------
    | Maximum failed OTP attempts
    |--------------------------------------------------------------------------
    */
    'max_attempts' => (int) env('ADMIN_2FA_MAX_ATTEMPTS', 5),

    /*
    |--------------------------------------------------------------------------
    | Minimum seconds between resend requests
    |--------------------------------------------------------------------------
    */
    'send_cooldown_seconds' => (int) env('ADMIN_2FA_SEND_COOLDOWN_SECONDS', 30),

];
