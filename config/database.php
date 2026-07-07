<?php

declare(strict_types=1);

return [
    'driver' => 'mariadb',
    'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
    'database' => $_ENV['DB_DATABASE'] ?? 'monolith',
    'username' => $_ENV['DB_USERNAME'] ?? 'monolith',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'charset' => 'utf8mb4',
];
