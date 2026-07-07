<?php
$totals = $testTree['totals'] ?? ['tests' => 0, 'passed' => 0, 'failed' => 0, 'errors' => 0];
$suites = $testTree['suites'] ?? [];
$hasResults = ($testStatus['ran_at'] ?? null) !== null || ($totals['tests'] ?? 0) > 0;
$passRate = $totals['tests'] > 0 ? (int) round(($totals['passed'] / $totals['tests']) * 100) : 0;
$failedTotal = $totals['failed'] + $totals['errors'];

$donutStops = [];
if ($totals['tests'] > 0) {
    $deg = 0.0;
    $slices = [
        ['n' => $totals['passed'], 'color' => '#10b981'],
        ['n' => $totals['failed'], 'color' => '#ef4444'],
        ['n' => $totals['errors'], 'color' => '#f59e0b'],
    ];
    foreach ($slices as $slice) {
        if ($slice['n'] <= 0) {
            continue;
        }
        $span = ($slice['n'] / $totals['tests']) * 360;
        $donutStops[] = $slice['color'] . ' ' . $deg . 'deg ' . ($deg + $span) . 'deg';
        $deg += $span;
    }
    if ($deg < 360) {
        $donutStops[] = '#94a3b8 ' . $deg . 'deg 360deg';
    }
}
$donutStyle = $donutStops !== []
    ? 'background: conic-gradient(' . implode(', ', $donutStops) . ')'
    : 'background: conic-gradient(#94a3b8 0deg 360deg)';

$colW = 120;
$graphWidth = max(520, count($suites) * $colW + 80);
$rootX = $graphWidth / 2;
$rootY = 40;
$graphEdges = [];
$graphSuites = [];
foreach ($suites as $i => $suite) {
    $x = 40 + $i * $colW + $colW / 2;
    $y = 118;
    $ok = array_reduce($suite['tests'], fn (bool $carry, array $t): bool => $carry && $t['status'] === 'passed', true);
    $graphEdges[] = ['x1' => $rootX, 'y1' => $rootY + 24, 'x2' => $x, 'y2' => $y - 20, 'suite' => null];
    $tests = [];
    foreach ($suite['tests'] as $j => $test) {
        $ty = 178 + $j * 24;
        $graphEdges[] = ['x1' => $x, 'y1' => $y + 20, 'x2' => $x, 'y2' => $ty - 8, 'suite' => $i];
        $tests[] = ['x' => $x, 'y' => $ty, 'name' => $test['name'], 'status' => $test['status'], 'description' => $test['description'] ?? null];
    }
    $graphSuites[] = ['i' => $i, 'x' => $x, 'y' => $y, 'label' => $suite['label'], 'count' => count($suite['tests']), 'ok' => $ok, 'tests' => $tests];
}
$maxTests = max(1, ...array_map(fn (array $s): int => count($s['tests']), $graphSuites ?: [['tests' => [1]]]));
$graphHeight = 178 + $maxTests * 24 + 48;

$testFill = static fn (string $status): string => match ($status) {
    'passed' => '#10b981',
    'error' => '#f59e0b',
    default => '#ef4444',
};
$ranAt = $testStatus['ran_at'] ?? null;
$statusUserId = (int) ($user['id'] ?? 0);
ob_start();
?>
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

<?php if ($hasResults && $ranAt): ?>
<div class="mb-6">
    <p class="text-sm font-medium uppercase tracking-wider text-muted">Last test run</p>
    <p class="mt-2 text-4xl font-semibold tabular-nums sm:text-5xl"><?= htmlspecialchars(time_ago((string) $ranAt), ENT_QUOTES) ?></p>
    <p class="mt-2 text-sm text-muted"><?= htmlspecialchars(format_user_datetime((string) $ranAt, $statusUserId), ENT_QUOTES) ?></p>
</div>
<?php endif; ?>

<?php if ($hasResults): ?>
<section class="card mb-6 p-6">
    <h2 class="text-sm font-semibold uppercase tracking-wider text-muted">Test overview</h2>

    <div class="mt-6 grid gap-8 lg:grid-cols-[1fr_auto_1fr] lg:items-center">
        <div class="grid grid-cols-3 gap-4 text-center sm:gap-6">
            <div>
                <p class="text-3xl font-semibold tabular-nums text-emerald-600 dark:text-emerald-400"><?= (int) $totals['passed'] ?></p>
                <p class="mt-1 text-xs uppercase tracking-wider text-muted">Passed</p>
            </div>
            <div>
                <p class="text-3xl font-semibold tabular-nums"><?= (int) $totals['tests'] ?></p>
                <p class="mt-1 text-xs uppercase tracking-wider text-muted">Total</p>
            </div>
            <div>
                <p class="text-3xl font-semibold tabular-nums <?= $failedTotal > 0 ? 'text-red-500' : 'text-slate-400' ?>"><?= (int) $failedTotal ?></p>
                <p class="mt-1 text-xs uppercase tracking-wider text-muted">Failed</p>
            </div>
        </div>

        <div class="flex justify-center">
            <div class="relative h-36 w-36">
                <div
                    class="h-full w-full rounded-full"
                    style="<?= htmlspecialchars($donutStyle, ENT_QUOTES) ?>"
                    role="img"
                    aria-label="<?= (int) $totals['passed'] ?> of <?= (int) $totals['tests'] ?> tests passed"
                ></div>
                <div class="absolute inset-4 flex flex-col items-center justify-center rounded-full bg-white dark:bg-slate-900">
                    <span class="text-2xl font-semibold tabular-nums"><?= $passRate ?>%</span>
                    <span class="text-xs text-muted">pass rate</span>
                </div>
            </div>
        </div>

        <div class="space-y-3 text-sm">
            <div class="flex items-center justify-between gap-4">
                <span class="text-muted">Last run</span>
                <span class="text-right"><?= htmlspecialchars(format_user_datetime((string) $ranAt, $statusUserId), ENT_QUOTES) ?></span>
            </div>
            <div class="flex items-center justify-between gap-4">
                <span class="text-muted">Result</span>
                <span class="<?= ($testStatus['passed'] ?? false) ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500' ?>">
                    <?= ($testStatus['passed'] ?? false) ? 'Passed' : 'Failed' ?>
                </span>
            </div>
            <div class="flex items-center justify-between gap-4">
                <span class="text-muted">Coverage</span>
                <span>
                    <?php if (($testStatus['coverage_percent'] ?? null) !== null): ?>
                        <?= htmlspecialchars((string) $testStatus['coverage_percent'], ENT_QUOTES) ?>%
                    <?php elseif ($testStatus['coverage_available'] ?? false): ?>
                        —
                    <?php else: ?>
                        <span class="text-muted">No driver</span>
                    <?php endif; ?>
                </span>
            </div>
            <?php if (($testStatus['coverage_percent'] ?? null) !== null): ?>
            <div class="h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">
                <div
                    class="h-full rounded-full bg-indigo-500 transition-all"
                    style="width: <?= min(100, max(0, (float) $testStatus['coverage_percent'])) ?>%"
                ></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($suites !== []): ?>
    <script type="application/json" id="test-tree-data"><?= json_encode($testTree, JSON_THROW_ON_ERROR) ?></script>
    <div class="mt-10" x-data="suiteGraph()" @keydown.escape.window="close()">
        <h3 class="text-sm font-semibold uppercase tracking-wider text-muted">Suite map</h3>
        <p class="mt-1 text-xs text-muted">Click a suite or test dot for details.</p>
        <div class="relative mt-4 overflow-x-auto rounded-lg border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-950/50">
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 <?= (int) $graphWidth ?> <?= (int) $graphHeight ?>"
                class="mx-auto block"
                style="min-width: <?= (int) $graphWidth ?>px; height: <?= (int) $graphHeight ?>px"
                role="img"
                aria-label="Test suite graph with <?= count($suites) ?> suites and <?= (int) $totals['tests'] ?> tests"
            >
                <?php foreach ($graphEdges as $edge): ?>
                <line
                    x1="<?= $edge['x1'] ?>" y1="<?= $edge['y1'] ?>"
                    x2="<?= $edge['x2'] ?>" y2="<?= $edge['y2'] ?>"
                    stroke="#64748b" stroke-width="1.5" stroke-opacity="0.55"
                />
                <?php endforeach; ?>
                <circle cx="<?= $rootX ?>" cy="<?= $rootY ?>" r="24" fill="#6366f133" stroke="#6366f1" stroke-width="2"/>
                <text x="<?= $rootX ?>" y="<?= $rootY + 5 ?>" text-anchor="middle" fill="#e2e8f0" font-size="11" font-weight="600">Tests</text>
                <?php foreach ($graphSuites as $suite): ?>
                <g>
                    <g
                        role="button"
                        tabindex="0"
                        class="cursor-pointer"
                        @click="openSuite(<?= (int) $suite['i'] ?>)"
                        @keydown.enter.prevent="openSuite(<?= (int) $suite['i'] ?>)"
                    >
                        <circle cx="<?= $suite['x'] ?>" cy="<?= $suite['y'] ?>" r="26" fill="transparent"/>
                        <circle
                            cx="<?= $suite['x'] ?>" cy="<?= $suite['y'] ?>" r="20"
                            fill="<?= $suite['ok'] ? '#10b98133' : '#ef444433' ?>"
                            stroke="<?= $suite['ok'] ? '#10b981' : '#ef4444' ?>"
                            stroke-width="2"
                            pointer-events="none"
                            :stroke="highlightSuite(<?= (int) $suite['i'] ?>) ? '#f8fafc' : '<?= $suite['ok'] ? '#10b981' : '#ef4444' ?>'"
                            :stroke-width="highlightSuite(<?= (int) $suite['i'] ?>) ? 3 : 2"
                        />
                        <text x="<?= $suite['x'] ?>" y="<?= $suite['y'] + 4 ?>" text-anchor="middle" fill="#f8fafc" font-size="10" font-weight="600" pointer-events="none"><?= (int) $suite['count'] ?></text>
                        <text x="<?= $suite['x'] ?>" y="<?= $suite['y'] + 36 ?>" text-anchor="middle" fill="#cbd5e1" font-size="10" pointer-events="none"><?= htmlspecialchars($suite['label'], ENT_QUOTES) ?></text>
                    </g>
                    <?php foreach ($suite['tests'] as $ti => $test): ?>
                    <g
                        role="button"
                        tabindex="0"
                        class="cursor-pointer"
                        @click="openTest(<?= (int) $suite['i'] ?>, <?= (int) $ti ?>)"
                        @keydown.enter.prevent="openTest(<?= (int) $suite['i'] ?>, <?= (int) $ti ?>)"
                    >
                        <circle cx="<?= $test['x'] ?>" cy="<?= $test['y'] ?>" r="12" fill="transparent"/>
                        <circle
                            cx="<?= $test['x'] ?>" cy="<?= $test['y'] ?>" r="7"
                            fill="<?= $testFill($test['status']) ?>"
                            pointer-events="none"
                            :stroke="isSelected(<?= (int) $suite['i'] ?>, <?= (int) $ti ?>) ? '#f8fafc' : 'none'"
                            :stroke-width="isSelected(<?= (int) $suite['i'] ?>, <?= (int) $ti ?>) ? 2 : 0"
                        />
                        <?php if (!empty($test['description'])): ?>
                        <title><?= htmlspecialchars($test['description'], ENT_QUOTES) ?></title>
                        <?php endif; ?>
                    </g>
                    <?php endforeach; ?>
                </g>
                <?php endforeach; ?>
            </svg>

            <div
                x-show="modal"
                x-cloak
                class="absolute inset-0 z-10 flex items-start justify-center overflow-y-auto bg-black/70 p-4 pt-8"
                @click.self="close()"
            >
                <div
                    class="graph-modal max-h-[75vh] w-full max-w-lg overflow-y-auto rounded-xl border border-slate-300 bg-white p-4 shadow-2xl dark:border-slate-600 dark:bg-slate-950"
                    @click.stop
                >
                    <template x-if="modal === 'suite'">
                        <div>
                            <div class="flex items-center justify-between gap-3">
                                <h4 class="text-base font-semibold" x-text="suiteLabel()"></h4>
                                <button type="button" class="graph-modal-muted shrink-0 hover:text-slate-900 dark:hover:text-white" @click="close()">✕</button>
                            </div>
                            <p x-show="suiteDescription()" class="mt-2 break-words text-sm leading-relaxed text-slate-600 dark:text-slate-300" x-text="suiteDescription()"></p>
                            <ul class="mt-4 divide-y divide-slate-200 dark:divide-slate-700">
                                <template x-for="(test, ti) in activeSuiteTests()" :key="ti">
                                    <li>
                                        <button
                                            type="button"
                                            class="grid w-full grid-cols-[minmax(0,1fr)_3.5rem] items-start gap-x-2 gap-y-1 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-800"
                                            @click="openTest(activeSuite, ti)"
                                            :title="test.description || ''"
                                        >
                                            <span class="break-words font-medium text-slate-900 dark:text-white" x-text="test.name"></span>
                                            <span class="justify-self-end rounded-full px-1.5 py-0.5 text-center text-[10px] font-medium capitalize leading-tight" :class="statusClass(test.status)" x-text="test.status"></span>
                                            <span x-show="test.description" class="col-span-2 break-words text-xs leading-snug text-slate-500 dark:text-slate-400" x-text="test.description"></span>
                                        </button>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </template>

                    <template x-if="modal === 'test'">
                        <div>
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0 flex-1">
                                    <p class="break-words font-medium text-slate-900 dark:text-white" x-text="selectedTest()?.name"></p>
                                    <p class="mt-1 text-xs uppercase tracking-wider" :class="statusTextClass(selectedTest()?.status)" x-text="selectedTest()?.status"></p>
                                </div>
                                <button type="button" class="graph-modal-muted shrink-0 hover:text-slate-900 dark:hover:text-white" @click="close()">✕</button>
                            </div>
                            <p x-show="selectedTest()?.description" class="mt-3 break-words text-sm leading-relaxed text-slate-600 dark:text-slate-300" x-text="selectedTest()?.description"></p>
                            <template x-if="selectedTest()?.detail">
                                <pre class="mt-3 max-h-48 overflow-auto rounded-lg bg-slate-100 p-3 font-mono text-xs text-slate-800 dark:bg-black dark:text-slate-100" x-text="selectedTest()?.detail"></pre>
                            </template>
                            <p x-show="!selectedTest()?.detail" class="mt-2 text-slate-600 dark:text-slate-300">Test passed — no failure output.</p>
                            <button
                                type="button"
                                class="mt-4 text-xs text-indigo-700 hover:text-indigo-600 dark:text-indigo-300 dark:hover:text-indigo-200"
                                @click="openSuite(activeTest.suite)"
                                x-show="activeTest"
                            >← Back to suite</button>
                        </div>
                    </template>
                </div>
            </div>
        </div>
        <ul class="mt-4 flex flex-wrap gap-2 text-xs text-muted">
            <li class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full bg-emerald-500"></span> Passed</li>
            <li class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full bg-red-500"></span> Failed</li>
            <li class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full bg-amber-500"></span> Error</li>
        </ul>
    </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<div class="grid gap-6 lg:grid-cols-2">
    <section class="card p-6">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-muted">Unit tests</h2>
        <?php if ($hasResults): ?>
        <dl class="mt-4 space-y-2 text-sm">
            <div class="flex justify-between gap-4">
                <dt class="text-muted">Last run</dt>
                <dd><?= $ranAt ? htmlspecialchars(format_user_datetime((string) $ranAt, $statusUserId), ENT_QUOTES) : '—' ?></dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-muted">Result</dt>
                <dd class="<?= ($testStatus['passed'] ?? false) ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500' ?>">
                    <?= ($testStatus['passed'] ?? false) ? 'Passed' : 'Failed' ?>
                </dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-muted">Tests</dt>
                <dd><?= (int) ($totals['tests'] ?: ($testStatus['tests'] ?? 0)) ?> passed of <?= (int) ($totals['tests'] ?: ($testStatus['tests'] ?? 0)) ?> (<?= (int) ($totals['failed'] ?: ($testStatus['failures'] ?? 0)) ?> failures, <?= (int) ($totals['errors'] ?: ($testStatus['errors'] ?? 0)) ?> errors)</dd>
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
            <?php if ($healthChecks === []): ?>
            <li class="text-muted">Not run yet — click Re-run checks below.</li>
            <?php endif; ?>
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

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('suiteGraph', () => ({
        suites: [],
        modal: null,
        activeSuite: null,
        activeTest: null,
        init() {
            const el = document.getElementById('test-tree-data');
            if (el) {
                this.suites = JSON.parse(el.textContent).suites || [];
            }
        },
        openSuite(i) {
            this.activeSuite = i;
            this.activeTest = null;
            this.modal = 'suite';
        },
        openTest(si, ti) {
            this.activeSuite = si;
            this.activeTest = { suite: si, test: ti };
            this.modal = 'test';
        },
        close() {
            this.modal = null;
            this.activeSuite = null;
            this.activeTest = null;
        },
        highlightSuite(i) {
            if (this.modal === 'suite' && this.activeSuite === i) return true;
            return this.activeTest?.suite === i;
        },
        isSelected(si, ti) {
            return this.modal === 'test' && this.activeTest?.suite === si && this.activeTest?.test === ti;
        },
        suiteLabel() {
            return this.activeSuite !== null ? this.suites[this.activeSuite]?.label ?? '' : '';
        },
        suiteDescription() {
            return this.activeSuite !== null ? this.suites[this.activeSuite]?.description ?? '' : '';
        },
        activeSuiteTests() {
            return this.activeSuite !== null ? this.suites[this.activeSuite]?.tests ?? [] : [];
        },
        selectedTest() {
            if (!this.activeTest) return null;
            return this.suites[this.activeTest.suite]?.tests[this.activeTest.test] ?? null;
        },
        statusClass(status) {
            if (status === 'passed') return 'bg-emerald-500/20 text-emerald-800 dark:bg-emerald-500/30 dark:text-emerald-100';
            if (status === 'error') return 'bg-amber-500/20 text-amber-800 dark:bg-amber-500/30 dark:text-amber-100';
            return 'bg-red-500/20 text-red-700 dark:bg-red-500/30 dark:text-red-100';
        },
        statusTextClass(status) {
            if (status === 'passed') return 'text-emerald-700 dark:text-emerald-300';
            if (status === 'error') return 'text-amber-700 dark:text-amber-300';
            return 'text-red-600 dark:text-red-300';
        },
    }));
});
</script>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
