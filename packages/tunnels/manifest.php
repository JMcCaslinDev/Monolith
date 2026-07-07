<?php

declare(strict_types=1);

return [
    'id' => 'tunnels',
    'project' => [
        'name' => 'Tunnels',
        'description' => 'Expose localhost to the internet with a live request inspector.',
        'icon' => '⇄',
        'path' => '/projects/tunnels',
        'permissions' => [
            'view' => 'projects.tunnels.view',
            'open' => 'projects.tunnels.open',
        ],
    ],
    'permissions' => [
        ['name' => 'projects.tunnels.view', 'description' => 'See Tunnels on dashboard', 'category' => 'projects'],
        ['name' => 'projects.tunnels.open', 'description' => 'Open Tunnels project', 'category' => 'projects'],
        ['name' => 'tunnels.create', 'description' => 'Create HTTP tunnels', 'category' => 'tunnels'],
        ['name' => 'tunnels.manage', 'description' => 'Stop and manage own tunnels', 'category' => 'tunnels'],
    ],
    'routes' => [
        ['method' => 'GET', 'path' => '/projects/tunnels', 'permission' => 'projects.tunnels.open', 'event' => 'page.viewed'],
        ['method' => 'GET', 'path' => '/projects/tunnels/api/state', 'permission' => 'projects.tunnels.open', 'event' => 'page.viewed', 'note' => 'Poll tunnels + requests'],
    ],
    'mutations' => [
        [
            'method' => 'POST',
            'path' => '/projects/tunnels/create',
            'permission' => 'tunnels.create',
            'event' => 'tunnels.tunnel.created',
        ],
        [
            'method' => 'POST',
            'path' => '/projects/tunnels/stop',
            'permission' => 'tunnels.manage',
            'event' => 'tunnels.tunnel.stopped',
        ],
    ],
    'events' => [
        ['type' => 'tunnels.tunnel.created', 'automatic' => false, 'note' => 'User created a tunnel'],
        ['type' => 'tunnels.tunnel.stopped', 'automatic' => false, 'note' => 'User stopped a tunnel'],
        ['type' => 'tunnels.tunnel.connected', 'automatic' => false, 'note' => 'CLI client connected'],
    ],
];
