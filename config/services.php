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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'marzsms' => [
        'base_url'  => env('MARZSMS_BASE_URL', 'https://sms.wearemarz.com/api/v1'),
        'api_key'   => env('MARZSMS_API_KEY'),
        'secret'    => env('MARZSMS_SECRET'),
        'sender_id' => env('MARZSMS_SENDER_ID', 'ElTech'),
    ],

    'marzpay' => [
        'base_url'       => env('MARZPAY_BASE_URL', 'https://wallet.wearemarz.com/api/v1'),
        'api_key'        => env('MARZPAY_API_KEY'),
        'api_secret'     => env('MARZPAY_API_SECRET'),
        'webhook_secret' => env('MARZPAY_WEBHOOK_SECRET'),
    ],

];
