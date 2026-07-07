<?php

declare(strict_types=1);

return [
    'name' => $_ENV['APP_NAME'] ?? 'Monolith',
    'env' => $_ENV['APP_ENV'] ?? 'local',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL),
    'url' => rtrim($_ENV['APP_URL'] ?? 'http://localhost:8000', '/'),
    'auth0' => [
        'domain' => $_ENV['AUTH0_DOMAIN'] ?? '',
        'client_id' => $_ENV['AUTH0_CLIENT_ID'] ?? '',
        'client_secret' => $_ENV['AUTH0_CLIENT_SECRET'] ?? '',
        'cookie_secret' => $_ENV['AUTH0_COOKIE_SECRET'] ?? '',
    ],
];
