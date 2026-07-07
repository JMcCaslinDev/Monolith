<?php

declare(strict_types=1);

/**
 * Tools package — internal utilities project.
 * Add tools here; register permissions and routes in this manifest.
 */
return [
    'id' => 'tools',
    'project' => [
        'name' => 'Tools',
        'description' => 'Internal utilities — format, validate, and convert data.',
        'icon' => '{}',
        'path' => '/projects/tools',
        'permissions' => [
            'view' => 'projects.tools.view',
            'open' => 'projects.tools.open',
        ],
    ],
    'permissions' => [
        ['name' => 'projects.tools.view', 'description' => 'See Tools on dashboard', 'category' => 'projects'],
        ['name' => 'projects.tools.open', 'description' => 'Open Tools project', 'category' => 'projects'],
        ['name' => 'tools.json-converter.use', 'description' => 'Use JSON converter', 'category' => 'tools'],
    ],
    'routes' => [
        ['method' => 'GET', 'path' => '/projects/tools', 'permission' => 'projects.tools.open', 'event' => 'page.viewed'],
        ['method' => 'GET', 'path' => '/projects/tools/json-converter', 'permission' => 'tools.json-converter.use', 'event' => 'tool.json_converter.used'],
    ],
    'events' => [
        ['type' => 'tool.json_converter.used', 'automatic' => false, 'note' => 'JSON converter opened'],
        ['type' => 'project.opened', 'automatic' => false, 'note' => 'User opened a project'],
    ],
];
