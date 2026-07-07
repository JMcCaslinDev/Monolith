<?php

/**
 * Living registry — core system permissions, routes, events.
 * Package manifests in packages/ are merged automatically.
 * Run: php scripts/check-registry.php && php scripts/check-coverage.php
 */

declare(strict_types=1);

use App\Projects\Registry;

$core = [
    'permissions' => [
        ['name' => 'pages.dashboard.view', 'description' => 'View dashboard', 'category' => 'pages'],
        ['name' => 'admin.events.view', 'description' => 'View audit log', 'category' => 'admin'],
        ['name' => 'admin.users.manage', 'description' => 'Manage users and roles', 'category' => 'admin'],
        ['name' => 'admin.permissions.manage', 'description' => 'Configure roles, permissions, grants', 'category' => 'permissions'],
    ],
    'routes' => [
        ['method' => 'GET', 'path' => '/', 'permission' => 'pages.dashboard.view', 'event' => 'page.viewed', 'note' => 'Authed only; guests see home'],
        ['method' => 'GET', 'path' => '/profile', 'permission' => null, 'event' => 'page.viewed', 'note' => 'Auth required; any logged-in user'],
        ['method' => 'GET', 'path' => '/admin', 'permission' => 'admin.hub', 'event' => 'page.viewed', 'note' => 'Composite: any admin.events.view | admin.users.manage | admin.permissions.manage'],
        ['method' => 'GET', 'path' => '/admin/events', 'permission' => 'admin.events.view', 'event' => 'page.viewed'],
        ['method' => 'GET', 'path' => '/admin/users', 'permission' => 'admin.users.manage', 'event' => 'page.viewed'],
        ['method' => 'GET', 'path' => '/admin/permissions', 'permission' => 'admin.permissions.manage', 'event' => 'page.viewed'],
        ['method' => 'GET', 'path' => '/admin/status', 'permission' => 'admin.hub', 'event' => 'page.viewed'],
    ],
    'mutations' => [
        ['method' => 'POST', 'path' => '/profile/theme', 'permission' => null, 'event' => 'settings.theme.changed', 'note' => 'Auth only'],
        ['method' => 'POST', 'path' => '/profile/navbar', 'permission' => null, 'event' => 'settings.navbar.changed', 'note' => 'Auth only'],
        ['method' => 'POST', 'path' => '/events/action', 'permission' => null, 'event' => 'action.performed', 'note' => 'Auth only; client actions'],
        ['method' => 'POST', 'path' => '/admin/permissions/role', 'permission' => 'admin.permissions.manage', 'event' => 'admin.role_permission.changed'],
        ['method' => 'POST', 'path' => '/admin/permissions/user/role', 'permission' => 'admin.permissions.manage', 'event' => 'admin.role.changed'],
        ['method' => 'POST', 'path' => '/admin/permissions/grant', 'permission' => 'admin.permissions.manage', 'event' => 'admin.grant.added'],
        ['method' => 'POST', 'path' => '/admin/permissions/grant/revoke', 'permission' => 'admin.permissions.manage', 'event' => 'admin.grant.removed'],
        ['method' => 'POST', 'path' => '/admin/users/role', 'permission' => 'admin.users.manage', 'event' => 'admin.role.changed'],
    ],
    'events' => [
        ['type' => 'page.viewed', 'automatic' => true, 'note' => 'Every routed request via dispatch() except recordPageView: false'],
        ['type' => 'page.not_found', 'automatic' => true, 'note' => 'Unknown route in public/index.php'],
        ['type' => 'permission.granted', 'automatic' => true, 'note' => 'Permission middleware pass + admin.hub'],
        ['type' => 'permission.denied', 'automatic' => true, 'note' => 'Permission middleware fail + admin.hub'],
        ['type' => 'action.performed', 'automatic' => false, 'note' => 'Client POST /events/action'],
        ['type' => 'project.opened', 'automatic' => false, 'note' => 'User opened a project'],
        ['type' => 'settings.navbar.changed', 'automatic' => false, 'note' => 'Navbar project pins updated'],
        ['type' => 'settings.theme.changed', 'automatic' => false, 'note' => 'Profile theme POST'],
        ['type' => 'admin.role.changed', 'automatic' => false, 'note' => 'User role assignment'],
        ['type' => 'admin.role_permission.changed', 'automatic' => false, 'note' => 'Role permission toggle'],
        ['type' => 'admin.grant.added', 'automatic' => false, 'note' => 'User permission grant'],
        ['type' => 'admin.grant.removed', 'automatic' => false, 'note' => 'User grant revoked'],
        ['type' => 'auth.login', 'automatic' => false, 'note' => 'Auth0 callback success'],
        ['type' => 'auth.logout', 'automatic' => false, 'note' => 'Logout'],
        ['type' => 'auth.login.started', 'automatic' => false, 'note' => 'Login redirect'],
        ['type' => 'auth.failed', 'automatic' => false, 'note' => 'Auth0 callback failure'],
    ],
];

return [
    'permissions' => array_merge($core['permissions'], Registry::packagePermissions()),
    'routes' => array_merge($core['routes'], Registry::packageRoutes()),
    'mutations' => $core['mutations'],
    'events' => array_merge($core['events'], Registry::packageEvents()),
    'projects' => Registry::projects(),
];
