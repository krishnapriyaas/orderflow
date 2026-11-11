<?php

declare(strict_types=1);

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

    'anthropic' => [
        // Without a key the app uses FakeOrderNoteExtractor, which sends every note to review.
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-haiku-5-5'),
        'url' => env('ANTHROPIC_API_URL', 'https://api.anthropic.com/v1/messages'),
        'version' => '2026-10-21',
        // Thinking is on by default and counts toward this limit.
        'max_tokens' => 4096,
        'timeout_seconds' => 60,
    ],

    'payment' => [
        'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET'),
        // Webhooks with an X-Timestamp further than this from now (past or future) are rejected.
        'webhook_tolerance_seconds' => 300,
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
