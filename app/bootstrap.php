<?php

declare(strict_types=1);

use App\Http\Middleware;
use App\Projects\Registry;
use App\Services\AuthService;
use App\Services\EventRecorder;
use App\Services\PermissionService;
use App\Services\TestStatusReader;
use App\Services\UserSettingsService;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);

if (is_file($root . '/.env')) {
    Dotenv::createImmutable($root)->safeLoad();
}

date_default_timezone_set('UTC');

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
}

function config(string $file): array
{
    static $cache = [];
    if (!isset($cache[$file])) {
        $cache[$file] = require dirname(__DIR__) . "/config/{$file}.php";
    }
    return $cache[$file];
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $c = config('database');
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $c['host'],
        $c['port'],
        $c['database'],
        $c['charset']
    );

    $pdo = new PDO($dsn, $c['username'], $c['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function events(): EventRecorder
{
    static $e = null;
    return $e ??= new EventRecorder(db());
}

function permissions(): PermissionService
{
    static $p = null;
    return $p ??= new PermissionService(db());
}

function user_settings(): UserSettingsService
{
    static $s = null;
    return $s ??= new UserSettingsService(db());
}

function test_status(): TestStatusReader
{
    static $t = null;
    return $t ??= new TestStatusReader();
}

function auth(): AuthService
{
    static $a = null;
    return $a ??= new AuthService(db(), permissions(), events());
}

function middleware(): Middleware
{
    static $m = null;
    return $m ??= new Middleware(auth(), permissions(), events());
}

function view(string $template, array $data = []): void
{
    $data['user'] = auth()->currentUser();
    $userPermissions = $data['user']
        ? permissions()->allForUser((int) $data['user']['id'])
        : [];
    $data['userPermissions'] = $userPermissions;
    if (!array_key_exists('permissions', $data)) {
        $data['permissions'] = $userPermissions;
    }
    $data['userRoles'] = $data['user'] ? user_roles((int) $data['user']['id']) : [];
    if ($data['user']) {
        $data['navbarProjects'] = navbar_projects_for_user(
            (int) $data['user']['id'],
            $userPermissions
        );
        $data['hasAdminAccess'] = has_admin_access($userPermissions);
    } else {
        $data['navbarProjects'] = [];
        $data['hasAdminAccess'] = false;
    }
    extract($data, EXTR_SKIP);
    require dirname(__DIR__) . "/resources/views/{$template}.php";
}

function package_view(string $package, string $template, array $data = []): void
{
    $data['user'] = auth()->currentUser();
    $userPermissions = $data['user']
        ? permissions()->allForUser((int) $data['user']['id'])
        : [];
    $data['userPermissions'] = $userPermissions;
    if (!array_key_exists('permissions', $data)) {
        $data['permissions'] = $userPermissions;
    }
    $data['userRoles'] = $data['user'] ? user_roles((int) $data['user']['id']) : [];
    if ($data['user']) {
        $data['navbarProjects'] = navbar_projects_for_user(
            (int) $data['user']['id'],
            $userPermissions
        );
        $data['hasAdminAccess'] = has_admin_access($userPermissions);
    }
    extract($data, EXTR_SKIP);
    ob_start();
    require dirname(__DIR__) . "/packages/{$package}/views/{$template}.php";
    $content = ob_get_clean();
    require dirname(__DIR__) . '/resources/views/layout.php';
}

/** @param list<string> $permissions */
function has_admin_access(array $permissions): bool
{
    return (bool) array_intersect(
        ['admin.events.view', 'admin.users.manage', 'admin.permissions.manage'],
        $permissions
    );
}

/** Composite gate for /admin hub — logs permission.granted/denied with admin.hub */
function require_admin_hub(): void
{
    $user = auth()->currentUser();
    if (!$user) {
        header('Location: /login');
        exit;
    }
    $uid = (int) $user['id'];
    if (has_admin_access(permissions()->allForUser($uid))) {
        events()->record('permission.granted', $uid, 'permission', 'admin.hub', ['permission' => 'admin.hub']);
        return;
    }
    events()->record('permission.denied', $uid, 'permission', 'admin.hub', ['permission' => 'admin.hub']);
    http_response_code(403);
    view('errors/403', ['title' => 'Forbidden', 'permission' => 'admin.hub']);
    exit;
}

/** @param list<string> $permissions */
/** @return list<array<string, mixed>> */
function navbar_projects_for_user(int $userId, array $permissions): array
{
    $openable = Registry::openableProjects($permissions);
    $byId = [];
    foreach ($openable as $project) {
        $byId[$project['id']] = $project;
    }
    $pinned = user_settings()->navbarProjectIds($userId, array_keys($byId));
    $items = [];
    foreach ($pinned as $id) {
        if (isset($byId[$id])) {
            $items[] = $byId[$id];
        }
    }
    return $items;
}

/** @param list<string> $permissions */
/** @return list<array<string, mixed>> */
function visible_projects_for_user(array $permissions): array
{
    return Registry::visibleProjects($permissions);
}

/** @return list<string> */
function user_roles(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT r.name FROM user_role ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? ORDER BY r.name'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function request_path(): string
{
    return parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
}

function record_page_view(): void
{
    $path = request_path();
    if ($path === '/health') {
        return;
    }

    $user = auth()->currentUser();
    events()->record(
        'page.viewed',
        $user ? (int) $user['id'] : null,
        'page',
        $path,
        ['page' => $path],
    );
}

/** @param list<string> $middleware */
function dispatch(callable $handler, array $middleware = [], bool $recordPageView = true): void
{
    $mw = middleware();
    foreach ($middleware as $name) {
        if ($name === 'auth') {
            $mw->auth();
        } elseif ($name === 'guest') {
            $mw->guest();
        } elseif (str_starts_with($name, 'perm:')) {
            $mw->permission(substr($name, 5));
        }
    }
    if ($recordPageView) {
        record_page_view();
    }
    $handler();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(403);
        exit('Invalid CSRF token');
    }
}

/** @param array<string, mixed> $event */
function event_payload(array $event): array
{
    if (is_string($event['payload'] ?? null)) {
        return json_decode($event['payload'], true) ?: [];
    }
    return is_array($event['payload'] ?? null) ? $event['payload'] : [];
}

/** @param list<array<string, mixed>> $events */
function group_events(array $events): array
{
    $buckets = [];
    foreach ($events as $event) {
        $key = $event['correlation_id'] ?? ('solo:' . $event['id']);
        $buckets[$key][] = $event;
    }

    $groups = [];
    foreach ($buckets as $correlationId => $items) {
        usort($items, fn ($a, $b) => (int) $a['id'] <=> (int) $b['id']);
        $primary = pick_primary_event($items);
        $related = array_values(array_filter($items, fn ($e) => (int) $e['id'] !== (int) $primary['id']));
        $groups[] = [
            'correlation_id' => str_starts_with((string) $correlationId, 'solo:') ? null : $correlationId,
            'primary' => enrich_event($primary),
            'related' => array_map('enrich_event', $related),
            'related_count' => count($related),
        ];
    }

    usort($groups, fn ($a, $b) => strcmp($b['primary']['created_at'], $a['primary']['created_at']));
    return $groups;
}

/** @param list<array<string, mixed>> $items */
function pick_primary_event(array $items): array
{
    $priority = ['page.viewed', 'page.not_found', 'project.opened', 'tool.json_converter.used', 'action.performed'];
    foreach ($priority as $type) {
        foreach ($items as $item) {
            if (($item['type'] ?? '') === $type) {
                return $item;
            }
        }
    }
    return $items[0];
}

/** @param array<string, mixed> $event */
function enrich_event(array $event): array
{
    $event['summary'] = event_summary($event);
    $event['payload_data'] = event_payload($event);
    return $event;
}

/** @param array<string, mixed> $event */
function event_summary(array $event): string
{
    $payload = is_string($event['payload'] ?? null)
        ? (json_decode($event['payload'], true) ?: [])
        : ($event['payload'] ?? []);

    return match ($event['type'] ?? '') {
        'page.viewed' => sprintf(
            'Viewed %s',
            $payload['page'] ?? $event['subject_id'] ?? $payload['path'] ?? 'page'
        ),
        'page.not_found' => sprintf('404 %s', $payload['path'] ?? $event['subject_id'] ?? ''),
        'permission.granted' => 'Allowed ' . ($payload['permission'] ?? $event['subject_id'] ?? ''),
        'permission.denied' => 'Denied ' . ($payload['permission'] ?? $event['subject_id'] ?? ''),
        'tool.json_converter.used' => 'Opened JSON converter',
        'action.performed' => match (true) {
            str_starts_with((string) ($payload['action'] ?? ''), 'json.') => 'JSON: '
                . substr($payload['action'], 5)
                . (isset($payload['input_bytes']) ? ' (' . $payload['input_bytes'] . ' bytes)' : ''),
            default => ($payload['action'] ?? $event['subject_id'] ?? 'action')
                . (isset($payload['input_bytes']) ? ' (' . $payload['input_bytes'] . ' bytes)' : ''),
        },
        'settings.theme.changed' => 'Theme → ' . ($payload['theme'] ?? '?'),
        'auth.login' => 'Signed in',
        'auth.logout' => 'Signed out',
        'auth.login.started' => 'Login redirect',
        'auth.failed' => 'Auth failed: ' . ($payload['reason'] ?? 'unknown'),
        'admin.role.changed' => sprintf(
            'Role → %s for user #%s',
            $payload['role'] ?? '?',
            $event['subject_id'] ?? '?'
        ),
        'admin.role_permission.changed' => ($payload['enabled'] ?? false ? 'Granted ' : 'Revoked ')
            . ($payload['permission'] ?? '?') . ' on ' . ($event['subject_id'] ?? 'role'),
        'admin.grant.added' => 'Grant ' . ($payload['permission'] ?? '?') . ' → user #' . ($event['subject_id'] ?? '?'),
        'admin.grant.removed' => 'Revoke ' . ($payload['permission'] ?? '?') . ' from user #' . ($event['subject_id'] ?? '?'),
        'project.opened' => 'Opened project ' . ($payload['project'] ?? $event['subject_id'] ?? '?'),
        'settings.navbar.changed' => 'Navbar pins updated',
        default => $event['subject_type']
            ? ($event['subject_type'] . ':' . ($event['subject_id'] ?? ''))
            : ($event['type'] ?? 'event'),
    };
}
