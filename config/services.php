<?php

return [

    'http' => [
        'verify_ssl' => env('HTTP_VERIFY_SSL', true),
    ],

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
        'server_ip' => env('RUNCLOUD_DEFAULT_SERVER_IP'),
        'timeout' => (int) env('RUNCLOUD_TIMEOUT', 30),
    ],

    'cloudflare' => [
        'base_url' => env('CLOUDFLARE_API_BASE_URL', 'https://api.cloudflare.com/client/v4'),
        'api_token' => env('CLOUDFLARE_API_TOKEN'),
        'api_key' => env('CLOUDFLARE_API_KEY'),
        'email' => env('CLOUDFLARE_EMAIL'),
        'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
        'timeout' => (int) env('CLOUDFLARE_TIMEOUT', 30),
    ],

    'internet_archive' => [
        'allowed_archive_hosts' => array_filter(array_map(
            'trim',
            explode(',', (string) env('INTERNET_ARCHIVE_ALLOWED_HOSTS', 'archive.org,archive-it.org,waybackmachinedownloader.com'))
        )),
        'timeout' => (int) env('INTERNET_ARCHIVE_TIMEOUT', 20),
        'poll_delay' => (int) env('INTERNET_ARCHIVE_POLL_DELAY', 60),
        'max_attempts' => (int) env('INTERNET_ARCHIVE_MAX_ATTEMPTS', 1440),
    ],

    'google_search_console' => [
        'enabled' => (bool) env('GOOGLE_SEARCH_CONSOLE_ENABLED', false),
        'credentials_path' => env('GOOGLE_SEARCH_CONSOLE_CREDENTIALS_PATH'),
        'timeout' => (int) env('GOOGLE_SEARCH_CONSOLE_TIMEOUT', 30),
        'indexing_daily_quota' => (int) env('GOOGLE_INDEXING_DAILY_QUOTA', 200),
        'indexing_max_rounds' => (int) env('GOOGLE_INDEXING_MAX_ROUNDS', 60),
    ],

    'sftp' => [
        'port' => (int) env('SFTP_PORT', 22),
        'username' => env('SFTP_USERNAME'),
        'password' => env('SFTP_PASSWORD'),
        'private_key' => env('SFTP_PRIVATE_KEY'),
        'private_key_path' => env('SFTP_PRIVATE_KEY_PATH'),
        'private_key_passphrase' => env('SFTP_PRIVATE_KEY_PASSPHRASE'),
        'host_fingerprint' => env('SFTP_HOST_FINGERPRINT'),
        'timeout' => (int) env('SFTP_TIMEOUT', 1800),
        'max_archive_entries' => (int) env('SFTP_MAX_ARCHIVE_ENTRIES', 10000),
        'webapps_root' => env('SFTP_WEBAPPS_ROOT', '/home/runcloud/webapps'),
        'allowed_roots' => array_filter(array_map(
            'trim',
            explode(',', (string) env('SFTP_ALLOWED_ROOTS', '/home/runcloud')),
        )),
        'default_index_files' => array_filter(array_map(
            'trim',
            explode(',', (string) env('SFTP_DEFAULT_INDEX_FILES', 'index.html,index.htm')),
        )),
    ],

];
