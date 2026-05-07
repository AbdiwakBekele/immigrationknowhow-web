<?php

$acceptAnyEnv = env('PHONE_OTP_ACCEPT_ANY');

return [

    /*
    |--------------------------------------------------------------------------
    | OTP driver
    |--------------------------------------------------------------------------
    |
    | - twilio: Use Twilio Verify (real SMS)
    | - cache:  Dev fallback (stores hashed OTP in cache and logs it)
    |
    */
    'driver' => env('PHONE_OTP_DRIVER', 'cache'),

    /*
    |--------------------------------------------------------------------------
    | Accept any 6-digit OTP
    |--------------------------------------------------------------------------
    |
    | When true, any 6-digit code succeeds after "Send code" (cache entry exists).
    | Set PHONE_OTP_ACCEPT_ANY=false in production when verifying the real SMS code.
    | If the variable is omitted, this defaults to true for easier local testing.
    |
    */
    'accept_any_six_digit' => $acceptAnyEnv === null
        ? true
        : filter_var($acceptAnyEnv, FILTER_VALIDATE_BOOLEAN),

];
