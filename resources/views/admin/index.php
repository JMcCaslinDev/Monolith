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
    <a href="/admin/status" class="card p-5 transition hover:border-slate-400 dark:hover:border-slate-600">
        <h3 class="font-medium">System status</h3>
        <p class="mt-1 text-sm text-muted">Unit tests, coverage, and registry health.</p>
    </a>
</div>

<section class="card mt-8 p-6">
    <h2 class="text-sm font-semibold uppercase tracking-wider text-muted">Navigation bar</h2>
    <p class="mt-2 text-sm text-muted">Show Admin in the top navigation bar (right side, before your profile menu).</p>
    <form method="post" action="/admin/navbar" class="mt-4">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
        <label class="flex cursor-pointer items-center gap-3">
            <input type="checkbox" name="visible" value="1" <?= !empty($navbarAdminVisible) ? 'checked' : '' ?> class="rounded border-slate-400">
            <span class="text-sm font-medium">Show Admin in navbar</span>
        </label>
        <button type="submit" class="btn-primary mt-4">Save</button>
    </form>
</section>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
