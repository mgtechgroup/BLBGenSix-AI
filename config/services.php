<?php

return [

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'organization' => env('OPENAI_ORGANIZATION'),
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET_KEY'),
        'key' => env('STRIPE_PUBLISHABLE_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'price_starter_monthly' => env('STRIPE_PRICE_STARTER'),
        'price_starter_yearly' => env('STRIPE_PRICE_STARTER_YEARLY'),
        'price_pro_monthly' => env('STRIPE_PRICE_PRO'),
        'price_pro_yearly' => env('STRIPE_PRICE_PRO_YEARLY'),
        'price_enterprise_monthly' => env('STRIPE_PRICE_ENTERPRISE'),
        'price_enterprise_yearly' => env('STRIPE_PRICE_ENTERPRISE_YEARLY'),
    ],

    'multi_scrobbler' => [
        'base_url' => env('MULTI_SCROBBLER_URL', 'http://multi-scrobbler:9078'),
        'api_key' => env('MULTI_SCROBBLER_API_KEY'),
        'cache_ttl' => env('MULTI_SCROBBLER_CACHE_TTL', 300),
        'webhook_secret' => env('LARAVEL_WEBHOOK_SECRET'),
    ],

];
