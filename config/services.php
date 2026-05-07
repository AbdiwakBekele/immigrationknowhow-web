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
