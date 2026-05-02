<?php

return [
    'modules' => [
        'Auth',
        'Security',
        'Verification',
        'ImageGeneration',
        'VideoGeneration',
        'TextGeneration',
        'BodyMapping',
        'SaaS',
        'Payments',
        'IncomeAutomation',
        'AdMonetization',
        'MultiRevenue',
        'Analytics',
        'Admin',
    ],

    'paths' => [
        'modules' => base_path('Modules'),
    ],

    'cache' => [
        'enabled' => env('MODULES_CACHE_ENABLED', false),
        'key' => 'laravel-modules',
        'lifetime' => 60,
    ],

    'register' => [
        'translations' => false,
        'files' => 'register',
    ],
];
