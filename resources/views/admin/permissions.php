<?php ob_start();
$rp = permissions()->rolePermissionMap();
$allPermissions = isset($allPermissions) && is_array($allPermissions) && isset($allPermissions[0]['name'])
    ? $allPermissions
    : permissions()->allPermissions();
?>
<div class="mb-6">
    <a href="/admin" class="text-sm text-muted hover:text-slate-700 dark:hover:text-slate-300">← Admin</a>
    <h1 class="mt-2 text-2xl font-semibold">Permissions</h1>
    <p class="mt-1 text-muted">Configure role access and per-user grants. Changes are audit-logged.</p>
</div>

<section class="card mb-8 overflow-x-auto p-4">
    <h2 class="mb-1 text-sm font-semibold uppercase tracking-wider text-muted">Role permissions</h2>
    <p class="mb-4 text-xs text-muted">Columns ordered by level: owner → admin → member → viewer</p>
    <table class="w-full min-w-[640px] text-left text-sm">
        <thead>
            <tr class="border-b border-slate-200 dark:border-slate-800">
                <th class="px-2 py-2">Permission</th>
                <?php foreach ($roles as $role): ?>
                <th class="px-2 py-2 text-center"><?= htmlspecialchars($role['name'], ENT_QUOTES) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
            <?php foreach ($allPermissions as $perm): ?>
            <tr>
                <td class="px-2 py-2">
                    <span class="font-mono text-xs"><?= htmlspecialchars($perm['name'], ENT_QUOTES) ?></span>
                    <span class="block text-xs text-muted"><?= htmlspecialchars($perm['description'] ?? '', ENT_QUOTES) ?></span>
                </td>
                <?php foreach ($roles as $role):
                    $checked = in_array((int) $perm['id'], $rp[(int) $role['id']] ?? [], true);
                ?>
                <td class="px-2 py-2 text-center">
                    <form method="post" action="/admin/permissions/role" class="inline">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
                        <input type="hidden" name="role" value="<?= htmlspecialchars($role['name'], ENT_QUOTES) ?>">
                        <input type="hidden" name="permission" value="<?= htmlspecialchars($perm['name'], ENT_QUOTES) ?>">
                        <input type="hidden" name="enabled" value="<?= $checked ? '0' : '1' ?>">
                        <button type="submit" class="rounded px-2 py-1 text-lg leading-none <?= $checked ? 'text-emerald-500' : 'text-slate-400' ?>" title="<?= $checked ? 'Revoke' : 'Grant' ?>">
                            <?= $checked ? '✓' : '○' ?>
                        </button>
                    </form>
                </td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="card p-4">
    <h2 class="mb-4 text-sm font-semibold uppercase tracking-wider text-muted">Users</h2>
    <p class="mb-4 text-sm text-muted">Select a user to view their permissions, change role, or add direct grants.</p>

    <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800">
        <table class="w-full min-w-[480px] text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-muted dark:border-slate-800 dark:bg-slate-900">
                <tr>
                    <th class="px-4 py-3">Email</th>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Role</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                <?php foreach ($users as $u):
                    $isSelected = ($selectedUserId ?? 0) === (int) $u['id'];
                ?>
                <tr class="<?= $isSelected ? 'bg-indigo-500/10' : 'table-row-hover' ?>">
                    <td class="px-4 py-3">
                        <a href="/admin/permissions?user_id=<?= (int) $u['id'] ?>" class="font-medium hover:text-indigo-600 dark:hover:text-indigo-400">
                            <?= htmlspecialchars($u['email'], ENT_QUOTES) ?>
                        </a>
                    </td>
                    <td class="px-4 py-3 text-muted"><?= htmlspecialchars($u['name'] ?? '—', ENT_QUOTES) ?></td>
                    <td class="px-4 py-3">
                        <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-medium dark:bg-slate-800"><?= htmlspecialchars($u['roles'] ?? 'none', ENT_QUOTES) ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (!empty($selectedUser)): ?>
    <div class="mt-8 border-t border-slate-200 pt-8 dark:border-slate-800">
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold"><?= htmlspecialchars($selectedUser['name'] ?? $selectedUser['email'], ENT_QUOTES) ?></h3>
                <p class="text-sm text-muted"><?= htmlspecialchars($selectedUser['email'], ENT_QUOTES) ?></p>
            </div>
            <form method="post" action="/admin/permissions/user/role" class="flex flex-wrap items-center gap-2">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
                <input type="hidden" name="user_id" value="<?= (int) $selectedUser['id'] ?>">
                <label class="text-sm text-muted">Role</label>
                <select name="role" class="input-field">
                    <?php
                    $currentRole = permissions()->userRoleName((int) $selectedUser['id']) ?? '';
                    foreach ($roles as $role):
                    ?>
                    <option value="<?= htmlspecialchars($role['name'], ENT_QUOTES) ?>" <?= $currentRole === $role['name'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($role['name'], ENT_QUOTES) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-primary">Save role</button>
            </form>
        </div>

        <h4 class="mb-3 text-sm font-semibold uppercase tracking-wider text-muted">Current permissions</h4>
        <?php if ($userBreakdown === []): ?>
        <p class="text-sm text-muted">No permissions — assign a role or add a direct grant.</p>
        <?php else: ?>
        <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-muted dark:border-slate-800 dark:bg-slate-900">
                    <tr>
                        <th class="px-4 py-2">Permission</th>
                        <th class="px-4 py-2">Source</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    <?php foreach ($userBreakdown as $item): ?>
                    <tr>
                        <td class="px-4 py-2">
                            <span class="font-mono text-xs"><?= htmlspecialchars($item['name'], ENT_QUOTES) ?></span>
                            <?php if ($item['description'] !== ''): ?>
                            <span class="block text-xs text-muted"><?= htmlspecialchars($item['description'], ENT_QUOTES) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-2">
                            <?php foreach ($item['sources'] as $source): ?>
                            <span class="mr-1 rounded-full px-2 py-0.5 text-xs font-medium <?= $source === 'grant' ? 'bg-amber-500/15 text-amber-700 dark:text-amber-300' : 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300' ?>">
                                <?= htmlspecialchars($source, ENT_QUOTES) ?>
                            </span>
                            <?php endforeach; ?>
                            <?php if (in_array('grant', $item['sources'], true)): ?>
                            <form method="post" action="/admin/permissions/grant/revoke" class="mt-1 inline">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
                                <input type="hidden" name="user_id" value="<?= (int) $selectedUser['id'] ?>">
                                <input type="hidden" name="permission" value="<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>">
                                <button type="submit" class="text-xs text-red-500 hover:text-red-400">Revoke grant</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($grantable !== []): ?>
        <form method="post" action="/admin/permissions/grant" class="mt-6 flex flex-wrap items-end gap-2">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
            <input type="hidden" name="user_id" value="<?= (int) $selectedUser['id'] ?>">
            <div>
                <label class="mb-1 block text-sm text-muted">Add direct grant</label>
                <select name="permission" class="input-field min-w-[16rem]">
                    <?php foreach ($grantable as $perm): ?>
                    <option value="<?= htmlspecialchars($perm['name'], ENT_QUOTES) ?>"><?= htmlspecialchars($perm['name'], ENT_QUOTES) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-primary">Grant</button>
        </form>
        <p class="mt-2 text-xs text-muted">Direct grants stack on top of role permissions. Only permissions not already granted directly are listed.</p>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <p class="mt-6 text-sm text-muted">Click a user above to manage their access.</p>
    <?php endif; ?>
</section>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
