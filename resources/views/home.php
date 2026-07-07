<?php ob_start(); ?>
<div class="flex min-h-[60vh] flex-col items-center justify-center text-center">
    <h1 class="text-4xl font-semibold tracking-tight">Monolith</h1>
    <p class="mt-4 max-w-md text-muted">Internal tools, one login. Data entry, formatters, and more — permission-gated and audit-logged.</p>
    <a href="/login" class="btn-primary mt-8 px-6 py-2.5">Sign in</a>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
