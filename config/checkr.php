<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Checkr API Configuration
    |--------------------------------------------------------------------------
    |
    | Configure your Checkr API credentials and settings here.
    | Get your API keys from: https://dashboard.checkr.com/api
    |
    */

    'api_key' => env('CHECKR_API_KEY'),
    
    'api_url' => env('CHECKR_API_URL', 'https://api.checkr-staging.com/v1'),
    
    // Use sandbox for development/testing
    'sandbox' => env('CHECKR_SANDBOX', true),
    
    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Checkr sends webhook notifications for status updates.
    | Configure your webhook secret for signature verification.
    |
    */
    
    'webhook_secret' => env('CHECKR_WEBHOOK_SECRET'),
    
    /*
    |--------------------------------------------------------------------------
    | Default Package
    |--------------------------------------------------------------------------
    |
    | The default background check package to use.
    | Common packages: basic_criminal, standard_criminal, professional
    |
    */
    
    'default_package' => env('CHECKR_DEFAULT_PACKAGE', 'basic_criminal'),
    
    /*
    |--------------------------------------------------------------------------
    | Work Locations
    |--------------------------------------------------------------------------
    |
    | Default work locations for candidates.
    |
    */
    
    'work_locations' => [
        ['country' => 'US', 'state' => 'CA'],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Expiration
    |--------------------------------------------------------------------------
    |
    | How long a cleared background check remains valid (in days).
    |
    */
    
    'expiration_days' => env('CHECKR_EXPIRATION_DAYS', 365),
    
    /*
    |--------------------------------------------------------------------------
    | Invitation Settings
    |--------------------------------------------------------------------------
    |
    | Settings for the candidate invitation flow.
    |
    */
    
    'invitation' => [
        // Checkr will collect payment from the candidate
        'candidate_pays' => true,
        
        // Custom invitation message
        'custom_message' => 'Please complete your background check to become a verified service provider on ImmigrationKnowHow.',
    ],
];
