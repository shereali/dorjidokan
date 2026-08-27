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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'sms' => [
        'provider' => env('SMS_PROVIDER', 'log'), // log | http | twilio | clicksend | custom
        'gateway' => env('SMS_GATEWAY', 'custom'), // twilio | clicksend | custom
        'url' => env('SMS_API_URL'),
        'key' => env('SMS_API_KEY'),
        'secret' => env('SMS_API_SECRET'),
        'sender' => env('SMS_SENDER'),
        'auth' => env('SMS_AUTH', 'basic'), // basic | bearer
        'fields' => [
            'to' => env('SMS_FIELD_TO', 'to'),
            'from' => env('SMS_FIELD_FROM', 'from'),
            'text' => env('SMS_FIELD_TEXT', 'text'),
        ],
    ],

];
