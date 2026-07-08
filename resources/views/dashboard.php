<?php ob_start(); ?>

<?php if (!empty($visibleProjects)): ?>
<section>
    <h2 class="mb-4 text-xs font-semibold uppercase tracking-wider text-muted">Projects</h2>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($visibleProjects as $project):
            $canOpen = in_array($project['permissions']['open'] ?? '', $userPermissions, true);
        ?>
        <?php if ($canOpen): ?>
        <a href="<?= htmlspecialchars($project['path'], ENT_QUOTES) ?>" class="card group p-5 transition hover:border-indigo-500/50">
            <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-500/15 font-mono text-sm text-indigo-600 dark:text-indigo-400"><?= htmlspecialchars($project['icon'] ?? '◆', ENT_QUOTES) ?></div>
            <h3 class="font-medium"><?= htmlspecialchars($project['name'], ENT_QUOTES) ?></h3>
            <p class="mt-1 text-sm text-muted"><?= htmlspecialchars($project['description'] ?? '', ENT_QUOTES) ?></p>
        </a>
        <?php else: ?>
        <div class="card p-5 opacity-60">
            <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-slate-500/15 font-mono text-sm text-slate-500"><?= htmlspecialchars($project['icon'] ?? '◆', ENT_QUOTES) ?></div>
            <h3 class="font-medium"><?= htmlspecialchars($project['name'], ENT_QUOTES) ?></h3>
            <p class="mt-1 text-sm text-muted">You can see this project but cannot open it.</p>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
