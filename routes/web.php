<?php

declare(strict_types=1);

use App\Projects\Registry;

$routes = [
    'GET /login' => fn () => dispatch(fn () => auth()->login(), ['guest']),
    'GET /auth/callback' => function (): void {
        auth()->handleCallback();
    },
    'GET /logout' => fn () => dispatch(fn () => auth()->logout(), ['auth']),

    'GET /profile' => fn () => dispatch(function (): void {
        $user = auth()->currentUser();
        $uid = (int) $user['id'];
        $perms = permissions()->allForUser($uid);
        $openable = Registry::openableProjects($perms);
        $pinned = user_settings()->navbarProjectIds($uid, array_column($openable, 'id'));
        view('profile', [
            'title' => 'Profile',
            'openableProjects' => $openable,
            'pinnedProjectIds' => $pinned,
        ]);
    }, ['auth']),

    'POST /profile/navbar' => fn () => dispatch(function (): void {
        verify_csrf();
        $user = auth()->currentUser();
        $uid = (int) $user['id'];
        $perms = permissions()->allForUser($uid);
        $openableIds = array_column(Registry::openableProjects($perms), 'id');
        $requested = $_POST['projects'] ?? [];
        if (!is_array($requested)) {
            $requested = [];
        }
        $pinned = array_values(array_intersect($openableIds, array_map('strval', $requested)));
        user_settings()->set($uid, 'navbar_projects', $pinned);
        events()->record('settings.navbar.changed', $uid, 'user', (string) $uid, [
            'projects' => $pinned,
        ]);
        header('Location: /profile');
        exit;
    }, ['auth']),

    'POST /profile/theme' => fn () => dispatch(function (): void {
        verify_csrf();
        $theme = trim((string) ($_POST['theme'] ?? ''));
        if (!in_array($theme, ['system', 'light', 'dark'], true)) {
            http_response_code(400);
            exit('Invalid theme');
        }
        $user = auth()->currentUser();
        events()->record('settings.theme.changed', (int) $user['id'], 'user', (string) $user['id'], ['theme' => $theme]);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }, ['auth'], recordPageView: false),

    'GET /' => fn () => dispatch(function (): void {
        if (auth()->currentUser()) {
            middleware()->permission('pages.dashboard.view');
            view('dashboard', [
                'title' => 'Dashboard',
                'visibleProjects' => visible_projects_for_user(permissions()->allForUser((int) auth()->currentUser()['id'])),
            ]);
        } else {
            view('home', ['title' => 'Monolith']);
        }
    }),

    'POST /events/action' => fn () => dispatch(function (): void {
        verify_csrf();
        $action = trim((string) ($_POST['action'] ?? ''));
        if ($action === '' || strlen($action) > 128 || !preg_match('/^[a-z0-9._-]+$/i', $action)) {
            http_response_code(400);
            exit('Invalid action');
        }
        $meta = ['action' => $action];
        if (isset($_POST['input_bytes'])) {
            $meta['input_bytes'] = max(0, (int) $_POST['input_bytes']);
        }
        $user = auth()->currentUser();
        events()->record('action.performed', (int) $user['id'], 'action', $action, $meta);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }, ['auth'], recordPageView: false),

    'GET /admin' => fn () => dispatch(function (): void {
        require_admin_hub();
        view('admin/index', ['title' => 'Administration']);
    }, ['auth']),

    'GET /admin/status' => fn () => dispatch(function (): void {
        require_admin_hub();
        view('admin/status', [
            'title' => 'System status',
            'testStatus' => test_status()->read(),
            'healthChecks' => test_status()->runHealthChecks(),
            'canRunTests' => test_status()->canRunFromWeb(),
            'flash' => $_SESSION['status_flash'] ?? null,
        ]);
        unset($_SESSION['status_flash']);
    }, ['auth']),

    'POST /admin/status/run' => fn () => dispatch(function (): void {
        verify_csrf();
        require_admin_hub();
        if (!test_status()->canRunFromWeb()) {
            http_response_code(403);
            exit('Tests can only run in local environment');
        }
        $code = test_status()->run();
        $_SESSION['status_flash'] = [
            'ok' => $code === 0,
            'message' => $code === 0 ? 'All tests passed.' : 'Tests failed — see results below.',
        ];
        header('Location: /admin/status');
        exit;
    }, ['auth'], recordPageView: false),

    'POST /admin/status/checks' => fn () => dispatch(function (): void {
        verify_csrf();
        require_admin_hub();
        header('Location: /admin/status');
        exit;
    }, ['auth'], recordPageView: false),

    'GET /admin/events' => fn () => dispatch(function (): void {
        view('admin/events', [
            'title' => 'Audit log',
            'groups' => group_events(events()->recent(200)),
        ]);
    }, ['auth', 'perm:admin.events.view']),

    'GET /admin/users' => fn () => dispatch(function (): void {
        $users = db()->query(
            'SELECT u.id, u.email, u.name, GROUP_CONCAT(r.name ORDER BY r.name) AS roles
             FROM users u
             LEFT JOIN user_role ur ON ur.user_id = u.id
             LEFT JOIN roles r ON r.id = ur.role_id
             GROUP BY u.id ORDER BY u.email'
        )->fetchAll();
        $roles = permissions()->sortRoleNames(
            db()->query('SELECT name FROM roles')->fetchAll(PDO::FETCH_COLUMN)
        );
        view('admin/users', ['title' => 'Users', 'users' => $users, 'roles' => $roles]);
    }, ['auth', 'perm:admin.users.manage']),

    'GET /admin/permissions' => fn () => dispatch(function (): void {
        $selectedUserId = (int) ($_GET['user_id'] ?? 0);
        $users = db()->query(
            'SELECT u.id, u.email, u.name, GROUP_CONCAT(r.name ORDER BY r.name) AS roles
             FROM users u
             LEFT JOIN user_role ur ON ur.user_id = u.id
             LEFT JOIN roles r ON r.id = ur.role_id
             GROUP BY u.id ORDER BY u.email'
        )->fetchAll();
        $selectedUser = null;
        $grants = [];
        $userBreakdown = [];
        $grantable = [];
        if ($selectedUserId > 0) {
            foreach ($users as $u) {
                if ((int) $u['id'] === $selectedUserId) {
                    $selectedUser = $u;
                    break;
                }
            }
            if ($selectedUser) {
                $grants = permissions()->userGrants($selectedUserId);
                $userBreakdown = permissions()->userPermissionBreakdown($selectedUserId);
                $grantNames = array_column($grants, 'name');
                $grantable = array_values(array_filter(
                    permissions()->allPermissions(),
                    fn (array $p) => !in_array($p['name'], $grantNames, true)
                ));
            }
        }
        view('admin/permissions', [
            'title' => 'Permissions',
            'permissions' => permissions()->allPermissions(),
            'roles' => permissions()->allRoles(),
            'users' => $users,
            'selectedUserId' => $selectedUserId,
            'selectedUser' => $selectedUser,
            'grants' => $grants,
            'userBreakdown' => $userBreakdown,
            'grantable' => $grantable,
        ]);
    }, ['auth', 'perm:admin.permissions.manage']),

    'POST /admin/permissions/role' => fn () => dispatch(function (): void {
        verify_csrf();
        $actor = auth()->currentUser();
        $role = trim((string) ($_POST['role'] ?? ''));
        $permission = trim((string) ($_POST['permission'] ?? ''));
        $enabled = ($_POST['enabled'] ?? '') === '1';
        if (!permissions()->isKnownRole($role) || !permissions()->isKnownPermission($permission)) {
            http_response_code(400);
            exit('Invalid input');
        }
        permissions()->setRolePermission($role, $permission, $enabled);
        events()->record('admin.role_permission.changed', (int) $actor['id'], 'role', $role, [
            'permission' => $permission,
            'enabled' => $enabled,
        ]);
        header('Location: /admin/permissions');
        exit;
    }, ['auth', 'perm:admin.permissions.manage']),

    'POST /admin/permissions/user/role' => fn () => dispatch(function (): void {
        verify_csrf();
        $actor = auth()->currentUser();
        $userId = (int) ($_POST['user_id'] ?? 0);
        $role = trim((string) ($_POST['role'] ?? ''));
        if ($userId < 1 || $role === '' || !permissions()->isKnownRole($role)) {
            http_response_code(400);
            exit('Invalid input');
        }
        permissions()->setRole($userId, $role);
        events()->record('admin.role.changed', (int) $actor['id'], 'user', (string) $userId, [
            'role' => $role,
            'via' => 'permissions_page',
        ]);
        header('Location: /admin/permissions?user_id=' . $userId);
        exit;
    }, ['auth', 'perm:admin.permissions.manage']),

    'POST /admin/permissions/grant' => fn () => dispatch(function (): void {
        verify_csrf();
        $actor = auth()->currentUser();
        $userId = (int) ($_POST['user_id'] ?? 0);
        $permission = trim((string) ($_POST['permission'] ?? ''));
        if ($userId < 1 || $permission === '' || !permissions()->isKnownPermission($permission)) {
            http_response_code(400);
            exit('Invalid input');
        }
        permissions()->grantUser($userId, $permission);
        events()->record('admin.grant.added', (int) $actor['id'], 'user', (string) $userId, [
            'permission' => $permission,
        ]);
        header('Location: /admin/permissions?user_id=' . $userId);
        exit;
    }, ['auth', 'perm:admin.permissions.manage']),

    'POST /admin/permissions/grant/revoke' => fn () => dispatch(function (): void {
        verify_csrf();
        $actor = auth()->currentUser();
        $userId = (int) ($_POST['user_id'] ?? 0);
        $permission = trim((string) ($_POST['permission'] ?? ''));
        if ($userId < 1 || $permission === '' || !permissions()->isKnownPermission($permission)) {
            http_response_code(400);
            exit('Invalid input');
        }
        permissions()->revokeUserGrant($userId, $permission);
        events()->record('admin.grant.removed', (int) $actor['id'], 'user', (string) $userId, [
            'permission' => $permission,
        ]);
        header('Location: /admin/permissions?user_id=' . $userId);
        exit;
    }, ['auth', 'perm:admin.permissions.manage']),

    'POST /admin/users/role' => fn () => dispatch(function (): void {
        verify_csrf();
        $actor = auth()->currentUser();
        $userId = (int) ($_POST['user_id'] ?? 0);
        $role = trim((string) ($_POST['role'] ?? ''));
        if ($userId < 1 || $role === '' || !permissions()->isKnownRole($role)) {
            http_response_code(400);
            exit('Invalid input');
        }
        permissions()->setRole($userId, $role);
        events()->record('admin.role.changed', (int) $actor['id'], 'user', (string) $userId, [
            'role' => $role,
            'via' => 'users_page',
        ]);
        header('Location: /admin/users');
        exit;
    }, ['auth', 'perm:admin.users.manage']),

    'GET /health' => fn () => dispatch(function (): void {
        header('Content-Type: application/json');
        $dbOk = false;
        try {
            db()->query('SELECT 1');
            $dbOk = true;
        } catch (Throwable) {
            // ponytail: health reports status, does not throw
        }
        echo json_encode(['status' => 'ok', 'database' => $dbOk ? 'connected' : 'unavailable']);
    }, recordPageView: false),
];

$root = dirname(__DIR__);
foreach (glob($root . '/packages/*/routes.php') ?: [] as $file) {
    /** @var array<string, callable> $packageRoutes */
    $packageRoutes = require $file;
    $routes = array_merge($routes, $packageRoutes);
}

return $routes;
