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

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'from' => env('SMS_FROM'),
        'http' => [
            'url' => env('SMS_HTTP_URL'),
            'method' => env('SMS_HTTP_METHOD', 'POST'),
            'format' => env('SMS_HTTP_FORMAT', 'json'),
            'token' => env('SMS_HTTP_TOKEN'),
            'token_header' => env('SMS_HTTP_TOKEN_HEADER', 'Authorization'),
            'token_prefix' => env('SMS_HTTP_TOKEN_PREFIX', 'Bearer '),
            'headers' => json_decode((string) env('SMS_HTTP_HEADERS', '{}'), true) ?: [],
            'to_field' => env('SMS_HTTP_TO_FIELD', 'to'),
            'message_field' => env('SMS_HTTP_MESSAGE_FIELD', 'message'),
            'from_field' => env('SMS_HTTP_FROM_FIELD', 'from'),
            'timeout' => (int) env('SMS_HTTP_TIMEOUT', 10),
            'connect_timeout' => (int) env('SMS_HTTP_CONNECT_TIMEOUT', 3),
        ],
    ],

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

];
