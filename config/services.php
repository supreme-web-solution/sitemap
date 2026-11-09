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

    'zernio' => [
        'base_url' => env('ZERNIO_BASE_URL', 'https://zernio.com/api/v1'),
        'api_key' => env('ZERNIO_API_KEY'),
    ],

    'mindcase' => [
        'key' => env('MINDCASE_API_KEY'),
        'base_url' => env('MINDCASE_BASE_URL', 'https://api.mindcase.co/v1'),
    ],

    /*
    | Anthropic — site planning, AI edits, follow-up sequence drafts, locale translation.
    | OpenAI — Command Center AI employee (config/command_center.php).
    | Zernio — SMS/WhatsApp; Command Center WhatsApp uses ZERNIO_WHATSAPP_* and ZERNIO_WEBHOOK_*.
    | OpenAI / OpenRouter — editor hero photo generation (see config/sites.php).
    */

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
        // Site editor chat: turn routing, edit planner, section CSS, design critic.
        'fast_model' => env('ANTHROPIC_MODEL_FAST', 'claude-haiku-4-5'),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

    'openrouter' => [
        'key' => env('OPENROUTER_API_KEY'),
        'url' => env('OPENROUTER_URL', 'https://openrouter.ai/api/v1'),
        'referer' => env('OPENROUTER_HTTP_REFERER', env('APP_URL')),
        'title' => env('OPENROUTER_APP_TITLE', env('APP_NAME')),
    ],

    /*
    | Site logos, gallery photos, AI images, and service cards. When configured,
    | uploads go to Cloudinary (stored as WebP) and are delivered with f_auto,q_auto.
    | CLOUDINARY_URL alone is enough; the split vars are also supported.
    */
    'cloudinary' => [
        'url' => env('CLOUDINARY_URL'),
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
        'key' => env('CLOUDINARY_API_KEY'),
        'secret' => env('CLOUDINARY_API_SECRET'),
        'folder' => env('CLOUDINARY_FOLDER', 'mapmatic'),
        'secure' => (bool) env('CLOUDINARY_SECURE', true),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Free mid-market rates (no API key). Used to charge Paystack / Flutterwave
    | in NGN when invoices are stored in USD. Cache for several hours — the
    | open endpoint only refreshes about once per day.
    */
    'exchange_rate' => [
        'url' => env('EXCHANGE_RATE_URL', 'https://open.er-api.com/v6/latest'),
    ],

    /*
    | Team plan billing (platform Stripe — not client invoice gateways).
    | When set with a plan.stripe_price_id, owners can self-serve upgrade via Checkout.
    */
    'billing' => [
        'stripe_secret' => env('STRIPE_SECRET'),
        'stripe_webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

];
