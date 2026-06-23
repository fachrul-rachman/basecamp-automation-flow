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

    'notion' => [
        'token' => env('NOTION_TOKEN'),
        'database_id' => env('NOTION_DATABASE_ID', '388901df-9413-80b8-bf37-c4a72374bb24'),
        'data_source_id' => env('NOTION_DATA_SOURCE_ID', '388901df-9413-8000-b842-000b948b6f20'),
        'version' => env('NOTION_VERSION', '2025-09-03'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'vision_model' => env('OPENAI_VISION_MODEL', 'gpt-4.1-mini'),
        'vision_max_attempts' => env('OPENAI_VISION_MAX_ATTEMPTS', 2),
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
