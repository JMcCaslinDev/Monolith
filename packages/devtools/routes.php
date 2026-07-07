<?php

declare(strict_types=1);

use Devtools\Catalog;
use Devtools\Toolbox;

require_once __DIR__ . '/src/Catalog.php';
require_once __DIR__ . '/src/Toolbox.php';

$devtoolsApp = function (string $toolSlug = ''): void {
    $user = auth()->currentUser();
    $uid = (int) $user['id'];
    $perms = permissions()->allForUser($uid);

    if ($toolSlug !== '' && !Catalog::canUse($toolSlug, $perms)) {
        http_response_code(403);
        view('errors/403', [
            'title' => 'Forbidden',
            'permission' => Catalog::find($toolSlug)
                ? Catalog::toolPermission(
                    Catalog::find($toolSlug)['category'],
                    $toolSlug
                )
                : 'devtools',
        ]);
        exit;
    }

    if ($toolSlug !== '') {
        $tool = Catalog::find($toolSlug);
        events()->record('devtools.tool.opened', $uid, 'devtools', $toolSlug, [
            'tool' => $toolSlug,
            'category' => $tool['category'] ?? '',
            'project' => 'devtools',
        ]);
    } else {
        events()->record('project.opened', $uid, 'project', 'devtools', ['project' => 'devtools']);
    }

    $accessible = Catalog::accessibleCategories($perms);
    $defaultSlug = $toolSlug;
    if ($defaultSlug === '') {
        foreach ($accessible as $cat) {
            if ($cat['tools'] !== []) {
                $defaultSlug = $cat['tools'][0]['slug'];
                break;
            }
        }
    }

    package_view('devtools', 'app', [
        'title' => 'Dev Tools',
        'categories' => $accessible,
        'activeTool' => $defaultSlug,
        'userPermissions' => $perms,
        'fullWidth' => true,
    ]);
};

$devtoolsRoutes = [
    'GET /projects/devtools' => fn () => dispatch(
        fn () => $devtoolsApp(''),
        ['auth', 'perm:projects.devtools.open']
    ),
    'POST /projects/devtools/process' => fn () => dispatch(function (): void {
        verify_csrf();
        $user = auth()->currentUser();
        $uid = (int) $user['id'];
        $perms = permissions()->allForUser($uid);
        $slug = trim((string) ($_POST['tool'] ?? ''));
        $action = trim((string) ($_POST['action'] ?? 'run'));
        if ($slug === '' || Catalog::find($slug) === null) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Unknown tool']);
            exit;
        }
        if (!Catalog::canUse($slug, $perms)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }
        $extra = [];
        if (isset($_POST['extra']) && is_string($_POST['extra'])) {
            $decoded = json_decode($_POST['extra'], true);
            if (is_array($decoded)) {
                $extra = $decoded;
            }
        }
        $result = Toolbox::process($slug, $action, [
            'input' => (string) ($_POST['input'] ?? ''),
            'input_b' => (string) ($_POST['input_b'] ?? ''),
            'extra' => $extra,
        ]);
        events()->record('devtools.tool.used', $uid, 'devtools', $slug, [
            'tool' => $slug,
            'action' => $action,
            'project' => 'devtools',
            'error' => isset($result['error']),
        ]);
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }, ['auth', 'perm:projects.devtools.open'], recordPageView: false),

    // ponytail: keep old Tools URLs working
    'GET /projects/tools' => fn () => dispatch(function (): void {
        header('Location: /projects/devtools', true, 301);
        exit;
    }, ['auth', 'perm:projects.devtools.open'], recordPageView: false),
    'GET /projects/tools/json-converter' => fn () => dispatch(function (): void {
        header('Location: /projects/devtools/json', true, 301);
        exit;
    }, ['auth', 'perm:projects.devtools.open'], recordPageView: false),
    'GET /tools/json-converter' => fn () => dispatch(function (): void {
        header('Location: /projects/devtools/json', true, 301);
        exit;
    }, ['auth', 'perm:projects.devtools.open'], recordPageView: false),
];

foreach (Catalog::tools() as $tool) {
    $slug = $tool['slug'];
    $perm = Catalog::toolPermission($tool['category'], $slug);
    $devtoolsRoutes["GET /projects/devtools/{$slug}"] = fn () => dispatch(
        fn () => $devtoolsApp($slug),
        ['auth', 'perm:' . $perm]
    );
}

return $devtoolsRoutes;
