<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'facebook' => [
        'page_id'      => env('FB_PAGE_ID'),
        'access_token' => env('FB_ACCESS_TOKEN'),
    ],

    'paymongo' => [
        'public_key'     => env('PAYMONGO_PUBLIC_KEY'),
        'secret_key'     => env('PAYMONGO_SECRET_KEY'),
        'webhook_secret' => env('PAYMONGO_WEBHOOK_SECRET'),
        'mode'           => env('PAYMONGO_MODE', 'test'),
        'allow_live'     => (bool) env('PAYMONGO_ALLOW_LIVE', false),
        'webhook_tolerance_seconds' => (int) env('PAYMONGO_WEBHOOK_TOLERANCE_SECONDS', 300),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'philsms' => [
        'api_key' => env('PHILSMS_API_KEY'),
        'sender_id' => env('PHILSMS_SENDER_ID', 'CafeGervacios'),
        'endpoint' => env('PHILSMS_ENDPOINT', 'https://app.philsms.com/api/v3/sms/send'),
    ],

];
