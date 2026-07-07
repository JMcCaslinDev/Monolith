<?php ob_start(); ?>
<div class="mb-8">
    <h1 class="text-2xl font-semibold">Profile &amp; settings</h1>
    <p class="mt-1 text-muted">Account info, navbar, and preferences.</p>
</div>

<div class="grid gap-6 lg:grid-cols-2">
    <section class="card p-6">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-muted">Account</h2>
        <dl class="mt-4 space-y-3 text-sm">
            <div>
                <dt class="text-muted">Name</dt>
                <dd class="font-medium"><?= htmlspecialchars($user['name'] ?? '—', ENT_QUOTES) ?></dd>
            </div>
            <div>
                <dt class="text-muted">Email</dt>
                <dd class="font-medium"><?= htmlspecialchars($user['email'], ENT_QUOTES) ?></dd>
            </div>
            <div>
                <dt class="text-muted">Role</dt>
                <dd><span class="rounded-full bg-indigo-500/15 px-2 py-0.5 text-xs font-medium text-indigo-600 dark:text-indigo-300"><?= htmlspecialchars($userRoles[0] ?? 'member', ENT_QUOTES) ?></span></dd>
            </div>
        </dl>
    </section>

    <section class="card p-6" x-data="themeSettings()">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-muted">Appearance</h2>
        <p class="mt-2 text-sm text-muted">Choose light, dark, or match your system setting.</p>
        <div class="mt-4 grid grid-cols-3 gap-2">
            <button type="button" @click="set('system')"
                :class="preference === 'system' ? 'border-indigo-500 bg-indigo-500/10 ring-1 ring-indigo-500' : 'border-slate-200 dark:border-slate-700'"
                class="rounded-lg border px-3 py-3 text-center text-sm transition hover:border-slate-400 dark:hover:border-slate-600">
                <span class="block text-lg">💻</span>
                <span class="mt-1 block font-medium">System</span>
            </button>
            <button type="button" @click="set('light')"
                :class="preference === 'light' ? 'border-indigo-500 bg-indigo-500/10 ring-1 ring-indigo-500' : 'border-slate-200 dark:border-slate-700'"
                class="rounded-lg border px-3 py-3 text-center text-sm transition hover:border-slate-400 dark:hover:border-slate-600">
                <span class="block text-lg">☀️</span>
                <span class="mt-1 block font-medium">Light</span>
            </button>
            <button type="button" @click="set('dark')"
                :class="preference === 'dark' ? 'border-indigo-500 bg-indigo-500/10 ring-1 ring-indigo-500' : 'border-slate-200 dark:border-slate-700'"
                class="rounded-lg border px-3 py-3 text-center text-sm transition hover:border-slate-400 dark:hover:border-slate-600">
                <span class="block text-lg">🌙</span>
                <span class="mt-1 block font-medium">Dark</span>
            </button>
        </div>
        <p class="mt-3 text-xs text-muted">Saved in this browser only.</p>
    </section>
</div>

<section class="card mt-6 p-6">
    <h2 class="text-sm font-semibold uppercase tracking-wider text-muted">Timezone</h2>
    <p class="mt-2 text-sm text-muted">Times across Monolith display in this zone. Defaults to your browser timezone, otherwise Pacific.</p>
    <form method="post" action="/profile/timezone" class="mt-4 flex flex-wrap items-end gap-3">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
        <label class="min-w-[16rem] flex-1">
            <span class="text-xs text-muted">Timezone</span>
            <select name="timezone" class="input-field mt-1 w-full">
                <?php
                $options = $timezoneOptions ?? timezone_options();
                $currentTz = $timezone ?? default_timezone();
                if (!isset($options[$currentTz])) {
                    $options = [$currentTz => $currentTz] + $options;
                }
                foreach ($options as $id => $label):
                ?>
                <option value="<?= htmlspecialchars($id, ENT_QUOTES) ?>" <?= $id === $currentTz ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="btn-primary">Save timezone</button>
    </form>
    <?php if (!($timezoneConfigured ?? false)): ?>
    <p class="mt-2 text-xs text-muted">Using Pacific until your browser timezone is detected or you save a choice.</p>
    <?php endif; ?>
</section>

<?php if (!empty($openableProjects)): ?>
<section class="card mt-6 p-6">
    <h2 class="text-sm font-semibold uppercase tracking-wider text-muted">Navbar</h2>
    <p class="mt-2 text-sm text-muted">Pin projects to the top navigation bar. Only projects you can open appear here.</p>
    <form method="post" action="/profile/navbar" class="mt-4 space-y-2">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
        <?php foreach ($openableProjects as $project):
            $checked = in_array($project['id'], $pinnedProjectIds ?? [], true);
        ?>
        <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-800">
            <input type="checkbox" name="projects[]" value="<?= htmlspecialchars($project['id'], ENT_QUOTES) ?>" <?= $checked ? 'checked' : '' ?> class="rounded border-slate-400">
            <span class="font-medium"><?= htmlspecialchars($project['name'], ENT_QUOTES) ?></span>
            <span class="text-xs text-muted"><?= htmlspecialchars($project['description'] ?? '', ENT_QUOTES) ?></span>
        </label>
        <?php endforeach; ?>
        <button type="submit" class="btn-primary mt-3">Save navbar</button>
    </form>
</section>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
