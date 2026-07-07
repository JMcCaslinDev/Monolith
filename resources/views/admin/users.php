<?php ob_start(); ?>
<div class="mb-8">
    <a href="/admin" class="text-sm text-muted hover:text-slate-700 dark:hover:text-slate-300">← Admin</a>
    <h1 class="mt-2 text-2xl font-semibold text-white">Users &amp; roles</h1>
    <p class="mt-1 text-slate-400">Assign one role per user. Use this for coworkers — not yourself if you're the owner.</p>
</div>

<div class="mb-8 rounded-xl border border-slate-800 bg-slate-900/50 p-5 text-sm text-slate-400">
    <p class="font-medium text-slate-300">Role levels (highest → lowest access for platform control)</p>
    <ul class="mt-3 space-y-2">
        <li><span class="font-medium text-indigo-300">owner</span> — everything: all tools + full admin. That's you. Don't change this on your own account.</li>
        <li><span class="font-medium text-slate-300">admin</span> — audit log + user management only. No tools unless you also give member access later.</li>
        <li><span class="font-medium text-slate-300">member</span> — dashboard + tools (JSON converter, etc.). Good default for coworkers.</li>
        <li><span class="font-medium text-slate-300">viewer</span> — dashboard only, read-only.</li>
    </ul>
</div>

<div class="overflow-hidden rounded-xl border border-slate-800">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-800 bg-slate-900/80 text-slate-500">
            <tr>
                <th class="px-4 py-3">Email</th>
                <th class="px-4 py-3">Name</th>
                <th class="px-4 py-3">Role</th>
                <th class="px-4 py-3">Change</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-800">
            <?php foreach ($users as $u): ?>
            <tr class="bg-slate-900/30 hover:bg-slate-900/50">
                <td class="px-4 py-3"><?= htmlspecialchars($u['email'], ENT_QUOTES) ?></td>
                <td class="px-4 py-3 text-slate-400"><?= htmlspecialchars($u['name'] ?? '—', ENT_QUOTES) ?></td>
                <td class="px-4 py-3">
                    <span class="rounded-full bg-slate-800 px-2 py-0.5 text-xs font-medium text-slate-300"><?= htmlspecialchars($u['roles'] ?? 'none', ENT_QUOTES) ?></span>
                </td>
                <td class="px-4 py-3">
                    <?php if ((int) $u['id'] === (int) $user['id']): ?>
                        <span class="text-slate-600">—</span>
                    <?php else: ?>
                    <form method="post" action="/admin/users/role" class="flex flex-wrap gap-2">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
                        <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                        <select name="role" class="rounded-lg border border-slate-700 bg-slate-950 px-2 py-1.5 text-sm">
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= htmlspecialchars($role, ENT_QUOTES) ?>" <?= ($u['roles'] ?? '') === $role ? 'selected' : '' ?>><?= htmlspecialchars($role, ENT_QUOTES) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium hover:bg-indigo-500">Save</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
