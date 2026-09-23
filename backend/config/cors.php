<?php

return [
    'paths' => ['api/*', 'broadcasting/auth', 'sanctum/csrf-cookie', 'up'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_values(array_filter(array_unique(array_merge(
        explode(',', env('FRONTEND_URLS', '')),
        [
            env('FRONTEND_URL', 'https://dorjidokan.softcredible.com'),
            env('APP_URL', 'https://dorjidokan.softcredible.com'),
            'http://localhost:3000',
            'http://127.0.0.1:3000',
        ]
    )))),
    'allowed_origins_patterns' => [
        '#^https?://.*\.softcredible\.com$#',
    ],
    'allowed_headers' => ['*'],
    'exposed_headers' => ['*'],
    'max_age' => 600,
    'supports_credentials' => true,
];
