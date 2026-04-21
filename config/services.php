<?php

return [
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
