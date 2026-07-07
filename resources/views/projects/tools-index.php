<?php ob_start(); ?>
<div class="mb-6">
    <a href="/" class="text-sm text-muted hover:text-slate-700 dark:hover:text-slate-300">← Dashboard</a>
    <h1 class="mt-2 text-2xl font-semibold">Tools</h1>
    <p class="mt-1 text-muted">Utilities and converters.</p>
</div>

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <?php if (in_array('tools.json-converter.use', $userPermissions, true)): ?>
    <a href="/projects/tools/json-converter" class="card group p-5 transition hover:border-indigo-500/50">
        <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-500/15 font-mono text-sm text-indigo-600 dark:text-indigo-400">{}</div>
        <h3 class="font-medium">JSON Converter</h3>
        <p class="mt-1 text-sm text-muted">Format, minify, and validate JSON in your browser.</p>
    </a>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
