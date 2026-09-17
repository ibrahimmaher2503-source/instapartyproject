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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'frontend' => [
        'revalidate_url' => env('FRONTEND_REVALIDATE_URL'),
        'revalidate_secret' => env('FRONTEND_REVALIDATE_SECRET'),
    ],

    'paymob' => [
        'api_url' => env('PAYMOB_API_URL', 'https://accept.paymob.com/api'),
        'checkout_url' => env('PAYMOB_CHECKOUT_URL', 'https://accept.paymob.com'),
        'api_key' => env('PAYMOB_API_KEY'),
        'hmac_secret' => env('PAYMOB_HMAC_SECRET'),
        'integration_id' => env('PAYMOB_INTEGRATION_ID'),
        'iframe_id' => env('PAYMOB_IFRAME_ID'),
        'health_check_order_id' => env('PAYMOB_HEALTH_CHECK_ORDER_ID'),
        'timeout' => (int) env('PAYMOB_TIMEOUT', 15),
    ],

    'firebase' => [
        // Absolute path to the service-account JSON, or the raw JSON string.
        'credentials' => env('FIREBASE_CREDENTIALS'),
        'project_id' => env('FIREBASE_PROJECT_ID'),
    ],

    'sms_misr' => [
        'username' => env('SMS_MISR_USERNAME'),
        'password' => env('SMS_MISR_PASSWORD'),
        'sender' => env('SMS_MISR_SENDER'),
        'environment' => (int) env('SMS_MISR_ENVIRONMENT', 2),
        'timeout' => (int) env('SMS_MISR_TIMEOUT', 30),
    ],

    'cloud_function' => [
        // Shared secret the Firestore onMessageCreated function sends in the
        // X-Cloud-Function-Secret header when calling the internal mirror endpoint.
        'secret' => env('CLOUD_FUNCTION_SHARED_SECRET'),
    ],

    'otp' => [
        'send_on_registration' => env('OTP_SEND_ON_REGISTRATION', false),
    ],

];
