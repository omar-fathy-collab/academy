<?php

return [
    'paths' => explode(',', env('CORS_PATHS', 'api/*,sanctum/csrf-cookie')),

    // Use env to define allowed origins. Default to none (safe) in production.
    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', '')),
    'allowed_origins_patterns' => [],

    'allowed_methods' => explode(',', env('CORS_ALLOWED_METHODS', 'GET,POST,PUT,DELETE,OPTIONS')),

    'allowed_headers' => explode(',', env('CORS_ALLOWED_HEADERS', '*')),

    'exposed_headers' => explode(',', env('CORS_EXPOSED_HEADERS', '')),

    'max_age' => env('CORS_MAX_AGE', 0),

    'supports_credentials' => env('CORS_SUPPORTS_CREDENTIALS', false),
];
