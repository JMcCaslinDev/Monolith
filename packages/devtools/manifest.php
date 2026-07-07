<?php

declare(strict_types=1);

use Devtools\Catalog;

require_once __DIR__ . '/src/Catalog.php';

$permissions = [
    ['name' => 'projects.devtools.view', 'description' => 'See Dev Tools on dashboard', 'category' => 'projects'],
    ['name' => 'projects.devtools.open', 'description' => 'Open Dev Tools project', 'category' => 'projects'],
];

foreach (Catalog::categories() as $catId => $cat) {
    $permissions[] = [
        'name' => Catalog::categoryPermission($catId),
        'description' => 'Use all ' . $cat['label'] . ' tools',
        'category' => 'devtools.' . $catId,
    ];
    foreach ($cat['tools'] as $tool) {
        $permissions[] = [
            'name' => Catalog::toolPermission($catId, $tool['slug']),
            'description' => 'Use ' . $tool['name'],
            'category' => 'devtools.' . $catId,
        ];
    }
}

$routes = [
    ['method' => 'GET', 'path' => '/projects/devtools', 'permission' => 'projects.devtools.open', 'event' => 'page.viewed'],
    ['method' => 'GET', 'path' => '/projects/tools', 'permission' => 'projects.devtools.open', 'event' => 'page.viewed', 'note' => '301 → /projects/devtools'],
    ['method' => 'GET', 'path' => '/projects/tools/json-converter', 'permission' => 'projects.devtools.open', 'event' => 'page.viewed', 'note' => '301 → /projects/devtools/json'],
    ['method' => 'GET', 'path' => '/tools/json-converter', 'permission' => 'projects.devtools.open', 'event' => 'page.viewed', 'note' => '301 → /projects/devtools/json'],
];

foreach (Catalog::tools() as $tool) {
    $routes[] = [
        'method' => 'GET',
        'path' => '/projects/devtools/' . $tool['slug'],
        'permission' => Catalog::toolPermission($tool['category'], $tool['slug']),
        'event' => 'devtools.tool.opened',
    ];
}

$events = [
    ['type' => 'devtools.tool.opened', 'automatic' => false, 'note' => 'Dev Tools tool page opened'],
    ['type' => 'devtools.tool.used', 'automatic' => false, 'note' => 'Dev Tools server action'],
];

return [
    'id' => 'devtools',
    'project' => [
        'name' => 'Dev Tools',
        'description' => 'Format, convert, encode, generate, and test data.',
        'icon' => '{}',
        'path' => '/projects/devtools',
        'permissions' => [
            'view' => 'projects.devtools.view',
            'open' => 'projects.devtools.open',
        ],
    ],
    'permissions' => $permissions,
    'routes' => $routes,
    'mutations' => [
        [
            'method' => 'POST',
            'path' => '/projects/devtools/process',
            'permission' => 'projects.devtools.open',
            'event' => 'devtools.tool.used',
            'note' => 'Permission re-checked per tool slug',
        ],
    ],
    'events' => $events,
];
