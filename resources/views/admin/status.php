<?php ob_start(); ?>
<div class="mb-6">
    <a href="/admin" class="text-sm text-muted hover:text-slate-700 dark:hover:text-slate-300">← Admin</a>
    <h1 class="mt-2 text-2xl font-semibold">System status</h1>
    <p class="mt-1 text-muted">Unit tests, coverage, and registry health checks.</p>
</div>

<?php if (!empty($flash)): ?>
<div class="mb-6 rounded-lg border px-4 py-3 text-sm <?= ($flash['ok'] ?? false) ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'border-red-500/40 bg-red-500/10 text-red-700 dark:text-red-300' ?>">
    <?= htmlspecialchars((string) ($flash['message'] ?? ''), ENT_QUOTES) ?>
</div>
<?php endif; ?>

<div class="grid gap-6 lg:grid-cols-2">
    <section class="card p-6">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-muted">Unit tests</h2>
        <?php if ($testStatus['ran_at'] ?? null): ?>
        <dl class="mt-4 space-y-2 text-sm">
            <div class="flex justify-between gap-4">
                <dt class="text-muted">Last run</dt>
                <dd><?= htmlspecialchars((string) $testStatus['ran_at'], ENT_QUOTES) ?></dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-muted">Result</dt>
                <dd class="<?= ($testStatus['passed'] ?? false) ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500' ?>">
                    <?= ($testStatus['passed'] ?? false) ? 'Passed' : 'Failed' ?>
                </dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-muted">Tests</dt>
                <dd><?= (int) ($testStatus['tests'] ?? 0) ?> (<?= (int) ($testStatus['failures'] ?? 0) ?> failures, <?= (int) ($testStatus['errors'] ?? 0) ?> errors)</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-muted">Coverage</dt>
                <dd>
                    <?php if (($testStatus['coverage_percent'] ?? null) !== null): ?>
                        <?= htmlspecialchars((string) $testStatus['coverage_percent'], ENT_QUOTES) ?>%
                    <?php elseif ($testStatus['coverage_available'] ?? false): ?>
                        —
                    <?php else: ?>
                        <span class="text-muted">Install pcov or xdebug</span>
                    <?php endif; ?>
                </dd>
            </div>
        </dl>
        <?php else: ?>
        <p class="mt-4 text-sm text-muted"><?= htmlspecialchars((string) ($testStatus['message'] ?? 'No results'), ENT_QUOTES) ?></p>
        <?php endif; ?>

        <?php if ($canRunTests): ?>
        <form method="post" action="/admin/status/run" class="mt-6">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
            <button type="submit" class="btn-primary">Run all tests</button>
        </form>
        <p class="mt-2 text-xs text-muted">Local environment only.</p>
        <?php else: ?>
        <p class="mt-4 text-xs text-muted">Run <code class="rounded bg-slate-200 px-1 dark:bg-slate-800">composer test</code> from the project root.</p>
        <?php endif; ?>
    </section>

    <section class="card p-6">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-muted">Registry checks</h2>
        <ul class="mt-4 space-y-3 text-sm">
            <?php foreach ($healthChecks as $check): ?>
            <li class="rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-800">
                <div class="flex items-center justify-between gap-2">
                    <span class="font-mono text-xs"><?= htmlspecialchars($check['name'], ENT_QUOTES) ?></span>
                    <span class="<?= $check['ok'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500' ?>">
                        <?= $check['ok'] ? 'OK' : 'FAIL' ?>
                    </span>
                </div>
                <?php if (!$check['ok'] && $check['output'] !== ''): ?>
                <pre class="mt-2 overflow-x-auto text-xs text-muted"><?= htmlspecialchars($check['output'], ENT_QUOTES) ?></pre>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <form method="post" action="/admin/status/checks" class="mt-4">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
            <button type="submit" class="btn-primary">Re-run checks</button>
        </form>
    </section>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
