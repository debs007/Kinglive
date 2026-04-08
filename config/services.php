<?php

return [

    'mailgun' => [
        'domain'   => env('MAILGUN_DOMAIN'),
        'secret'   => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme'   => 'https',
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Agora RTC
    |--------------------------------------------------------------------------
    */
    'agora' => [
        'app_id'          => env('AGORA_APP_ID'),
        'app_certificate' => env('AGORA_APP_CERTIFICATE'),
        'token_expiry'    => env('AGORA_TOKEN_EXPIRY', 21600), // 6 hours
    ],

    /*
    |--------------------------------------------------------------------------
    | Firebase Cloud Messaging
    |--------------------------------------------------------------------------
    */
    'fcm' => [
        'server_key' => env('FCM_SERVER_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Twilio (OTP SMS)
    |--------------------------------------------------------------------------
    */
    'twilio' => [
        'sid'   => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
        'from'  => env('TWILIO_FROM'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Apple IAP
    |--------------------------------------------------------------------------
    */
    'apple' => [
        'shared_secret' => env('APPLE_SHARED_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AWS S3
    |--------------------------------------------------------------------------
    */
    'aws' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'bucket' => env('AWS_BUCKET', 'king-live-media'),
        'url'    => env('AWS_URL'),
    ],

];
