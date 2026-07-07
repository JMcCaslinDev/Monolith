<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$key = "{$method} {$path}";

/** @var array<string, callable> $routes */
$routes = require dirname(__DIR__) . '/routes/web.php';

if (isset($routes[$key])) {
    $routes[$key]();
    return;
}

http_response_code(404);
$user = auth()->currentUser();
events()->record(
    'page.not_found',
    $user ? (int) $user['id'] : null,
    'page',
    $path,
    ['method' => $method],
);
view('errors/404', ['title' => 'Not found']);
