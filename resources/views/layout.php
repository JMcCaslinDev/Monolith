<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if (!empty($user)): ?>
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
    <meta name="timezone-configured" content="<?= saved_user_timezone((int) $user['id']) ? '1' : '0' ?>">
    <?php endif; ?>
    <script nonce="<?= htmlspecialchars(csp_nonce(), ENT_QUOTES) ?>">
    (function () {
        var p = localStorage.getItem('monolith-theme') || 'system';
        var dark = p === 'dark' || (p === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
        if (dark) document.documentElement.classList.add('dark');
    })();
    </script>
    <title><?= htmlspecialchars($title ?? 'Monolith', ENT_QUOTES) ?></title>
    <?php
    $manifestPath = dirname(__DIR__, 2) . '/public/build/.vite/manifest.json';
    $isLocal = (config('app')['env'] ?? 'production') === 'local';
    $useBuild = is_file($manifestPath) && !$isLocal;
    if ($useBuild):
        $entry = json_decode(file_get_contents($manifestPath), true)['resources/js/app.js'] ?? [];
        $js = $entry['file'] ?? null;
        $cssFiles = $entry['css'] ?? [];
    ?>
        <?php foreach ($cssFiles as $css): ?><link rel="stylesheet" href="/build/<?= htmlspecialchars($css, ENT_QUOTES) ?>"><?php endforeach; ?>
        <?php if ($js): ?><script type="module" src="/build/<?= htmlspecialchars($js, ENT_QUOTES) ?>"></script><?php endif; ?>
    <?php elseif ($isLocal): ?>
        <script type="module" src="http://localhost:5173/@vite/client"></script>
        <script type="module" src="http://localhost:5173/resources/js/app.js"></script>
        <link rel="stylesheet" href="http://localhost:5173/resources/css/app.css">
    <?php else: error_log('Vite build manifest missing: run pnpm build'); ?>
    <?php endif; ?>
</head>
<body class="page-bg min-h-screen antialiased">
    <?php if (!empty($user)): require __DIR__ . '/partials/nav.php'; endif; ?>
    <main class="<?= !empty($fullWidth) ? 'px-0 py-0' : 'mx-auto max-w-6xl px-6 py-8' ?>">
        <?= $content ?? '' ?>
    </main>
</body>
</html>
