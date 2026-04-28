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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL', 'http://localhost') . '/auth/google/callback'),
        'guzzle' => [
            'verify' => env('GOOGLE_OAUTH_CA_BUNDLE')
                ? base_path(env('GOOGLE_OAUTH_CA_BUNDLE'))
                : env('GOOGLE_OAUTH_VERIFY_SSL', true),
        ],
    ],

    'github' => [
        'releases' => [
            'repository' => env('GITHUB_RELEASE_REPOSITORY', 'xshrrln/NSync'),
            'token' => env('GITHUB_RELEASE_TOKEN'),
            'cache_minutes' => env('GITHUB_RELEASE_CACHE_MINUTES', 10),
            'timeout' => env('GITHUB_RELEASE_TIMEOUT', 12),
            'fallback_version' => env('APP_VERSION', 'v0.0.0'),
        ],
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_SUPPORT_MODEL', 'gpt-5.4-mini'),
        'timeout' => env('OPENAI_SUPPORT_TIMEOUT', 12),
    ],

    'support_ai' => [
        'enabled' => env('SUPPORT_AI_ENABLED', false),
        'assistant_name' => env('SUPPORT_AI_ASSISTANT_NAME', 'NSync Assistant'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
