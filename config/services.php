<?php

return [
    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'from_number' => env('TWILIO_FROM_NUMBER'),
        // VA... Twilio Verify Service SID
        'verify_service_sid' => env('TWILIO_VERIFY_SERVICE_SID', env('TWILIO_VERIFY_SID')),
        'verify_code_length' => (int) env('TWILIO_VERIFY_CODE_LENGTH', 6),
        'fake' => env('TWILIO_FAKE', false),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'maps_api_key' => ($k = env('GOOGLE_MAPS_API_KEY')) !== null && (string) $k !== ''
            ? trim((string) $k)
            : '',
    ],

    /*
    | Stripe — library checkout reads these. Accepts common alternate env names.
    | Publishable: STRIPE_KEY (preferred) or STRIPE_PUBLISHABLE_KEY.
    | Secret: STRIPE_SECRET (preferred) or STRIPE_SECRET_KEY.
    */
    'stripe' => [
        'key' => ($k = env('STRIPE_KEY') ?: env('STRIPE_PUBLISHABLE_KEY')) !== null && (string) $k !== ''
            ? trim((string) $k)
            : '',
        'secret' => ($s = env('STRIPE_SECRET') ?: env('STRIPE_SECRET_KEY')) !== null && (string) $s !== ''
            ? trim((string) $s)
            : '',
        'webhook_secret' => ($w = env('STRIPE_WEBHOOK_SECRET')) !== null && (string) $w !== ''
            ? trim((string) $w)
            : '',
        'ai_assistant_price_id' => ($p = env('STRIPE_AI_ASSISTANT_PRICE_ID')) !== null && (string) $p !== ''
            ? trim((string) $p)
            : '',
    ],

    /*
    | Apple In-App Purchase (App Store Server API) — required for iOS digital purchases.
    | Create products in App Store Connect matching library item apple_product_id (or default prefix + uuid).
    */
    'apple_iap' => [
        'bundle_id' => ($b = env('APPLE_BUNDLE_ID', 'com.immigrantknowhow.ikhapp')) !== null && (string) $b !== ''
            ? trim((string) $b)
            : 'com.immigrantknowhow.ikhapp',
        'issuer_id' => ($i = env('APPLE_ISSUER_ID')) !== null && (string) $i !== ''
            ? trim((string) $i)
            : '',
        'key_id' => ($k = env('APPLE_KEY_ID')) !== null && (string) $k !== ''
            ? trim((string) $k)
            : '',
        'private_key' => ($p = env('APPLE_PRIVATE_KEY')) !== null && (string) $p !== ''
            ? trim((string) $p)
            : '',
        'private_key_path' => ($path = env('APPLE_PRIVATE_KEY_PATH')) !== null && (string) $path !== ''
            ? trim((string) $path)
            : '',
        'sandbox' => filter_var(env('APPLE_IAP_SANDBOX', true), FILTER_VALIDATE_BOOL),
        'ai_assistant_product_id' => ($a = env('APPLE_AI_ASSISTANT_PRODUCT_ID', 'com.immigrantknowhow.ikhapp.ai_assistant.monthly')) !== null && (string) $a !== ''
            ? trim((string) $a)
            : 'com.immigrantknowhow.ikhapp.ai_assistant.monthly',
        'library_product_prefix' => ($l = env('APPLE_LIBRARY_PRODUCT_PREFIX', 'com.immigrantknowhow.ikhapp.library')) !== null && (string) $l !== ''
            ? trim((string) $l)
            : 'com.immigrantknowhow.ikhapp.library',
        'library_ebook_product_id' => ($e = env('APPLE_LIBRARY_EBOOK_PRODUCT_ID', 'EBOOK_TO2026')) !== null && (string) $e !== ''
            ? trim((string) $e)
            : 'EBOOK_TO2026',
        'library_ebook_slug' => ($s = env('APPLE_LIBRARY_EBOOK_SLUG')) !== null && (string) $s !== ''
            ? trim((string) $s)
            : '',
    ],

    'openai' => [
        'api_key' => ($k = env('OPENAI_API_KEY')) !== null && (string) $k !== ''
            ? trim((string) $k)
            : '',
        'model' => ($m = env('OPENAI_MODEL')) !== null && (string) $m !== ''
            ? trim((string) $m)
            : 'gpt-4o-mini',
        'summary_max_pdf_bytes' => (int) env('OPENAI_SUMMARY_MAX_PDF_BYTES', 5242880),
    ],  // ← this was missing

    'inbound_email' => [
        'webhook_secret' => env('INBOUND_EMAIL_WEBHOOK_SECRET', ''),
    ],
];
