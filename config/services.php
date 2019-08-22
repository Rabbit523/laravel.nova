<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Stripe, Mailgun, SparkPost and others. This file provides a sane
    | default location for this type of information, allowing packages
    | to have a conventional place to find your various credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
    ],

    'ses' => [
        'key' => env('SES_KEY'),
        'secret' => env('SES_SECRET'),
        'region' => env('SES_REGION', 'us-east-1'),
    ],

    'sparkpost' => [
        'secret' => env('SPARKPOST_SECRET'),
    ],

    'stripe' => [
        'model' => App\User::class,
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'product' => env('STRIPE_PRODUCT'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'connect_secret' => env('STRIPE_CONNECT_SECRET'),
        'platform_id' => env('STRIPE_PLATFORM_ID', 'acct_1D1Dx5HndoGdr5c6'),
        'application_fee' => env('STRIPE_CONNECT_FEE', 1.4)
    ],

    'microsoft' => [
        'client_id' => env('MS_CLIENT_ID', ''),
        'client_secret' => env('MS_CLIENT_SECRET', ''),
        'redirect' => env('MS_CALLBACK', 'https://dev.kinchaku.com/auth/microsoft/callback'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID', ''),
        'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
        'redirect' => env('GOOGLE_CALLBACK', 'https://dev.kinchaku.com/auth/google/callback'),
        'redirect_for_integration' => env('GOOGLE_CALLBACK_FOR_INTEGRATION'),
    ],

    'kinchaku' => [
        'key' => env('KINCHAKU_KEY'),
    ],

    'freee' => [
        'client_id' => env('FREEE_CLIENT_ID', ''),
        'client_secret' => env('FREEE_CLIENT_SECRET', ''),
        'redirect' => env('FREEE_CALLBACK', 'urn:ietf:wg:oauth:2.0:oob'),
    ],

    'hubspot' => [
        'client_id'     => env('HUBSPOT_KEY'),
        'client_secret' => env('HUBSPOT_SECRET'),
        'redirect'      => env('HUBSPOT_REDIRECT_URI', 'https://dev.kinchaku.com/auth/hubspot/callback'),
    ],
];
