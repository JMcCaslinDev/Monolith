<?php ob_start(); ?>
<h1 class="text-2xl font-semibold">403</h1>
<p class="mt-2 text-slate-400">You don't have permission: <code class="text-red-400"><?= htmlspecialchars($permission ?? '', ENT_QUOTES) ?></code></p>
<p class="mt-4"><a href="/" class="text-indigo-400 hover:text-indigo-300">Back to dashboard</a></p>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
