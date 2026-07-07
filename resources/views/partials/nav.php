<?php
$roleLabel = $userRoles[0] ?? 'member';
$initials = strtoupper(substr($user['name'] ?? $user['email'], 0, 1));
?>
<header class="header-bar sticky top-0 z-50">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-3">
        <div class="flex items-center gap-8">
            <a href="/" class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">Monolith</a>
            <nav class="hidden items-center gap-1 sm:flex">
                <a href="/" class="nav-link">Dashboard</a>
                <?php foreach ($navbarProjects ?? [] as $project): ?>
                    <a href="<?= htmlspecialchars($project['path'], ENT_QUOTES) ?>" class="nav-link"><?= htmlspecialchars($project['name'], ENT_QUOTES) ?></a>
                <?php endforeach; ?>
            </nav>
        </div>

        <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false">
            <button
                type="button"
                @click="open = !open"
                class="flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm hover:border-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-slate-600"
            >
                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-600 text-xs font-medium text-white"><?= htmlspecialchars($initials, ENT_QUOTES) ?></span>
                <span class="hidden max-w-[10rem] truncate text-slate-700 dark:text-slate-300 sm:inline"><?= htmlspecialchars($user['name'] ?? $user['email'], ENT_QUOTES) ?></span>
                <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div
                x-show="open"
                x-cloak
                @click.outside="open = false"
                class="absolute right-0 mt-2 w-56 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900"
            >
                <a href="/profile" @click="open = false" class="block border-b border-slate-200 px-4 py-3 transition hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800">
                    <p class="truncate text-sm font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($user['name'] ?? 'User', ENT_QUOTES) ?></p>
                    <p class="truncate text-xs text-muted"><?= htmlspecialchars($user['email'], ENT_QUOTES) ?></p>
                    <span class="mt-2 inline-block rounded-full bg-indigo-500/15 px-2 py-0.5 text-xs font-medium text-indigo-600 dark:text-indigo-300"><?= htmlspecialchars($roleLabel, ENT_QUOTES) ?></span>
                </a>
                <a href="/profile" @click="open = false" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800">Profile &amp; settings</a>
                <?php if (!empty($hasAdminAccess)): ?>
                <a href="/admin" @click="open = false" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800">Admin</a>
                <?php endif; ?>
                <a href="/logout" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800">Sign out</a>
            </div>
        </div>
    </div>
</header>
