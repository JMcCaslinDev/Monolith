<?php ob_start(); ?>
<div class="mb-6">
    <a href="/" class="text-sm text-muted hover:text-slate-700 dark:hover:text-slate-300">← Dashboard</a>
    <h1 class="mt-2 text-2xl font-semibold">Administration</h1>
    <p class="mt-1 text-muted">Platform management — users, permissions, and audit log.</p>
</div>

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <?php if (in_array('admin.events.view', $userPermissions, true)): ?>
    <a href="/admin/events" class="card p-5 transition hover:border-slate-400 dark:hover:border-slate-600">
        <h3 class="font-medium">Audit log</h3>
        <p class="mt-1 text-sm text-muted">See who did what — logins, tools, permission changes.</p>
    </a>
    <?php endif; ?>
    <?php if (in_array('admin.permissions.manage', $userPermissions, true)): ?>
    <a href="/admin/permissions" class="card p-5 transition hover:border-slate-400 dark:hover:border-slate-600">
        <h3 class="font-medium">Permissions</h3>
        <p class="mt-1 text-sm text-muted">Role matrix, user grants, and access control.</p>
    </a>
    <?php endif; ?>
    <?php if (in_array('admin.users.manage', $userPermissions, true)): ?>
    <a href="/admin/users" class="card p-5 transition hover:border-slate-400 dark:hover:border-slate-600">
        <h3 class="font-medium">Users &amp; roles</h3>
        <p class="mt-1 text-sm text-muted">Assign member, viewer, admin, or owner to accounts.</p>
    </a>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
