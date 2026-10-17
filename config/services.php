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

    'webpush' => [
        'vapid_public_key' => env('VAPID_PUBLIC_KEY', 'BMqew5VNSqT4zM3FihrpaIFTFPe0_cEd5elg5RlTxMuA3d7-6NCFvT3P_0ayY_6OB1_QMinLXKIPz9U7rHtpTPc'),
        'vapid_private_key' => env('VAPID_PRIVATE_KEY', 'PhoY8vVPg9YNRoRPC_VsSPbNeFipdEpgW5V9T-d37P0'),
        'vapid_subject' => env('VAPID_SUBJECT', env('APP_URL', 'mailto:admin@masroofi.com')),
    ],

    'ai_advisor' => [
        'gemini_key' => env('GEMINI_API_KEY'),
        'openai_key' => env('OPENAI_API_KEY'),
        'provider' => env('AI_ADVISOR_PROVIDER', 'auto'),
    ],

];
