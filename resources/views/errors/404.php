<?php ob_start(); ?>
<h1 class="text-2xl font-semibold">404</h1>
<p class="mt-2 text-slate-400">Page not found.</p>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
