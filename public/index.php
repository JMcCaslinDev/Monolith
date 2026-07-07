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

// ponytail: SEO-friendly public blog URLs (/blog/my-post-slug)
if ($method === 'GET' && str_starts_with($path, '/blog/') && $path !== '/blog/sitemap.xml') {
    $slug = substr($path, 6);
    if ($slug !== '' && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
        $_GET['slug'] = $slug;
        if (isset($routes['GET /blog/post'])) {
            $routes['GET /blog/post']();
            return;
        }
    }
}

http_response_code(404);
$user = auth()->currentUser();
if (should_record_page_event($path)) {
    events()->record(
        'page.not_found',
        $user ? (int) $user['id'] : null,
        'page',
        $path,
        ['method' => $method, 'path' => $path],
    );
}
view('errors/404', ['title' => 'Not found']);
