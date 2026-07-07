<?php ob_start();
/** @var list<array<string, mixed>> $groups */
/** @var int $page */
/** @var int $perPage */
/** @var int $total */
/** @var int $totalPages */
$from = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
$to = min($page * $perPage, $total);
?>
<div class="mb-6">
    <a href="/admin" class="text-sm text-muted hover:text-slate-700 dark:hover:text-slate-300">← Admin</a>
    <h1 class="mt-2 text-2xl font-semibold">Audit log</h1>
    <p class="mt-1 text-muted">Newest first — <?= number_format($perPage) ?> events per page. Click a row for details; related events from the same request are grouped.</p>
</div>

<script type="application/json" id="audit-data"><?= json_encode($groups, JSON_THROW_ON_ERROR) ?></script>

<div
    class="card flex max-h-[calc(100vh-9rem)] flex-col overflow-hidden"
    x-data="auditLog()"
    @keydown.escape.window="close()"
>
    <div class="min-h-0 flex-1 overflow-y-auto overflow-x-auto">
        <table class="w-full min-w-[640px] text-left text-sm">
            <thead class="sticky top-0 z-10 border-b border-slate-200 bg-slate-50 text-muted dark:border-slate-800 dark:bg-slate-900">
                <tr>
                    <th class="px-4 py-3">Time</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Summary</th>
                    <th class="px-4 py-3">Actor</th>
                    <th class="px-4 py-3">Linked</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                <?php if ($groups === []): ?>
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-muted">No events yet.</td>
                </tr>
                <?php endif; ?>
                <?php foreach ($groups as $idx => $group):
                    $primary = $group['primary'];
                ?>
                <tr class="table-row-hover" @click="openAt(<?= (int) $idx ?>)">
                    <td class="whitespace-nowrap px-4 py-3 text-muted">
                        <div><?= htmlspecialchars((string) ($primary['created_at_display'] ?? $primary['created_at']), ENT_QUOTES) ?></div>
                        <?php if (!empty($primary['created_at_ago'])): ?>
                        <div class="text-xs"><?= htmlspecialchars((string) $primary['created_at_ago'], ENT_QUOTES) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 font-mono text-xs"><?= htmlspecialchars((string) $primary['type'], ENT_QUOTES) ?></td>
                    <td class="px-4 py-3 font-medium"><?= htmlspecialchars((string) $primary['summary'], ENT_QUOTES) ?></td>
                    <td class="px-4 py-3 text-muted"><?= htmlspecialchars((string) ($primary['actor_email'] ?? '—'), ENT_QUOTES) ?></td>
                    <td class="px-4 py-3">
                        <?php if ($group['related_count'] > 0): ?>
                        <span class="rounded-full bg-indigo-500/15 px-2 py-0.5 text-xs font-medium text-indigo-600 dark:text-indigo-300">
                            +<?= (int) $group['related_count'] ?> related
                        </span>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 dark:border-slate-800">
        <p class="text-sm text-muted">
            <?php if ($total === 0): ?>
            No events
            <?php else: ?>
            Showing <?= number_format($from) ?>–<?= number_format($to) ?> of <?= number_format($total) ?> events
            <?php endif; ?>
        </p>
        <?php if ($totalPages > 1): ?>
        <div class="flex items-center gap-2 text-sm">
            <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>" class="rounded bg-slate-200 px-3 py-1.5 hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700">Previous</a>
            <?php endif; ?>
            <span class="text-muted">Page <?= $page ?> of <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page + 1 ?>" class="rounded bg-slate-200 px-3 py-1.5 hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700">Next</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <div
        x-show="modal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
        @click.self="close()"
    >
        <div class="card max-h-[85vh] w-full max-w-lg overflow-y-auto p-6 shadow-2xl" @click.stop>
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold" x-text="active?.primary?.summary"></h2>
                    <p class="mt-1 text-sm text-muted" x-text="active?.primary?.type + ' · ' + (active?.primary?.created_at_display ?? active?.primary?.created_at)"></p>
                    <p class="text-xs text-muted" x-show="active?.primary?.created_at_ago" x-text="active?.primary?.created_at_ago"></p>
                </div>
                <button type="button" class="text-muted hover:text-slate-900 dark:hover:text-white" @click="close()">✕</button>
            </div>

            <dl class="mt-6 space-y-3 text-sm" x-show="active">
                <div>
                    <dt class="text-muted">Actor</dt>
                    <dd x-text="active.primary.actor_email ?? '—'"></dd>
                </div>
                <div x-show="active.correlation_id">
                    <dt class="text-muted">Request ID</dt>
                    <dd class="font-mono text-xs" x-text="active.correlation_id"></dd>
                </div>
                <div>
                    <dt class="text-muted">Subject</dt>
                    <dd class="font-mono text-xs" x-text="(active.primary.subject_type ?? '') + (active.primary.subject_id ? ':' + active.primary.subject_id : '')"></dd>
                </div>
                <div>
                    <dt class="text-muted">IP</dt>
                    <dd x-text="active.primary.ip ?? '—'"></dd>
                </div>
                <div>
                    <dt class="text-muted">Payload</dt>
                    <dd class="mt-1 overflow-x-auto rounded-lg bg-slate-100 p-3 font-mono text-xs dark:bg-slate-950" x-text="JSON.stringify(active.primary.payload_data, null, 2)"></dd>
                </div>
            </dl>

            <div class="mt-6" x-show="active?.related?.length">
                <h3 class="text-sm font-semibold uppercase tracking-wider text-muted">Related events</h3>
                <ul class="mt-3 space-y-2">
                    <template x-for="(rel, i) in active.related" :key="rel.id ?? i">
                        <li class="rounded-lg border border-slate-200 p-3 dark:border-slate-800">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-medium" x-text="rel.summary"></span>
                                <span class="font-mono text-xs text-muted" x-text="rel.type"></span>
                            </div>
                            <pre class="mt-2 overflow-x-auto font-mono text-xs text-muted" x-text="JSON.stringify(rel.payload_data, null, 2)"></pre>
                        </li>
                    </template>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('auditLog', () => ({
        groups: [],
        modal: false,
        active: null,
        init() {
            const el = document.getElementById('audit-data');
            if (el) {
                this.groups = JSON.parse(el.textContent);
            }
        },
        openAt(idx) {
            this.openGroup(this.groups[idx]);
        },
        openGroup(group) {
            this.active = group;
            this.modal = true;
            document.body.classList.add('overflow-hidden');
        },
        close() {
            this.modal = false;
            this.active = null;
            document.body.classList.remove('overflow-hidden');
        },
    }));
});
</script>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
