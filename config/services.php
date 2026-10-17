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

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    // コンビニ払い（KOMOJU 管理画面 → 設定 → API鍵 / Webhook）
    'komoju' => [
        'secret' => env('KOMOJU_SECRET'),
        'webhook_secret' => env('KOMOJU_WEBHOOK_SECRET'),
        // コンビニ払いの手数料（円）。申込者の負担として合計金額に足す
        'fee' => (int) env('KOMOJU_FEE', 0),
    ],

    // 運営への通知先（入金完了メールのBCCなど）
    'kizuna' => [
        'admin_email' => env('KIZUNA_ADMIN_EMAIL'),
    ],

];
