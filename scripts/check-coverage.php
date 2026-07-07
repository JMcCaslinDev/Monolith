#!/usr/bin/env php
<?php
// ponytail: fail if registry routes/mutations drift from code or events missing
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$registry = config('registry');
/** @var array<string, callable> $routes */
$routes = require dirname(__DIR__) . '/routes/web.php';

$failed = false;

foreach ($registry['routes'] as $route) {
    $key = $route['method'] . ' ' . $route['path'];
    if (!isset($routes[$key])) {
        fwrite(STDERR, "FAIL: route not registered: {$key}\n");
        $failed = true;
    }
}

foreach ($registry['mutations'] ?? [] as $mutation) {
    $key = $mutation['method'] . ' ' . $mutation['path'];
    if (!isset($routes[$key])) {
        fwrite(STDERR, "FAIL: mutation not registered: {$key}\n");
        $failed = true;
    }
}

$permNames = array_column($registry['permissions'], 'name');
foreach ($registry['routes'] as $route) {
    $perm = $route['permission'] ?? null;
    if ($perm === null || $perm === 'admin.hub') {
        continue;
    }
    if (!in_array($perm, $permNames, true)) {
        fwrite(STDERR, "FAIL: route permission not in registry: {$perm}\n");
        $failed = true;
    }
}

foreach ($registry['mutations'] ?? [] as $mutation) {
    $perm = $mutation['permission'] ?? null;
    if ($perm !== null && !in_array($perm, $permNames, true)) {
        fwrite(STDERR, "FAIL: mutation permission not in registry: {$perm}\n");
        $failed = true;
    }
}

$bootstrap = file_get_contents(dirname(__DIR__) . '/app/bootstrap.php');
foreach ($registry['events'] as $event) {
    if ($event['automatic'] ?? false) {
        continue;
    }
    $type = $event['type'];
    if (!str_contains($bootstrap, "'{$type}'")) {
        fwrite(STDERR, "FAIL: event_summary missing: {$type}\n");
        $failed = true;
    }
}

$sources = file_get_contents(dirname(__DIR__) . '/routes/web.php');
foreach (glob(dirname(__DIR__) . '/packages/*/routes.php') ?: [] as $file) {
    $sources .= file_get_contents($file);
}
foreach ($registry['mutations'] ?? [] as $mutation) {
    if (!str_contains($sources, $mutation['event'])) {
        fwrite(STDERR, "FAIL: mutation {$mutation['method']} {$mutation['path']} missing event {$mutation['event']}\n");
        $failed = true;
    }
}

if ($failed) {
    exit(1);
}

$pageRoutes = count($registry['routes']);
$mutations = count($registry['mutations'] ?? []);
$events = count($registry['events']);
echo "OK: coverage — {$pageRoutes} pages, {$mutations} mutations, {$events} event types\n";
