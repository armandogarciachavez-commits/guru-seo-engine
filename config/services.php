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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'pexels' => [
        'key' => env('PEXELS_API_KEY'),
    ],

    // Google Search Console (monitoreo SEO vía service account).
    // GOOGLE_SERVICE_ACCOUNT_JSON: ruta al JSON de credenciales del service account.
    // Por defecto storage/app/google/service-account.json (fuera del docroot público).
    'google' => [
        'service_account_json' => env(
            'GOOGLE_SERVICE_ACCOUNT_JSON',
            storage_path('app/google/service-account.json')
        ),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
