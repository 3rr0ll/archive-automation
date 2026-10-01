<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'runcloud' => [
        'base_url' => env('RUNCLOUD_API_BASE_URL', 'https://manage.runcloud.io/api/v3'),
        'api_key' => env('RUNCLOUD_API_KEY'),
        'api_secret' => env('RUNCLOUD_API_SECRET'),
        'api_token' => env('RUNCLOUD_API_TOKEN'),
        'server_id' => env('RUNCLOUD_DEFAULT_SERVER_ID'),
        'timeout' => (int) env('RUNCLOUD_TIMEOUT', 30),
    ],

];
