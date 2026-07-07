<?php

declare(strict_types=1);

return [
    'id' => 'stickies',
    'project' => [
        'name' => 'Stickies',
        'description' => 'Quick colorful notes on a draggable board — search, categories, and fullscreen editing.',
        'icon' => '📝',
        'path' => '/projects/stickies',
        'permissions' => [
            'view' => 'projects.stickies.view',
            'open' => 'projects.stickies.open',
        ],
    ],
    'permissions' => [
        ['name' => 'projects.stickies.view', 'description' => 'See Stickies on dashboard', 'category' => 'projects'],
        ['name' => 'projects.stickies.open', 'description' => 'Open Stickies project', 'category' => 'projects'],
        ['name' => 'stickies.manage', 'description' => 'Create, edit, move, and delete own stickies', 'category' => 'stickies'],
    ],
    'routes' => [
        ['method' => 'GET', 'path' => '/projects/stickies', 'permission' => 'projects.stickies.open', 'event' => 'page.viewed'],
        ['method' => 'GET', 'path' => '/projects/stickies/api/notes', 'permission' => 'stickies.manage', 'event' => 'page.viewed', 'note' => 'Load stickies'],
    ],
    'mutations' => [
        [
            'method' => 'POST',
            'path' => '/projects/stickies/note/save',
            'permission' => 'stickies.manage',
            'event' => 'stickies.note.saved',
        ],
        [
            'method' => 'POST',
            'path' => '/projects/stickies/note/delete',
            'permission' => 'stickies.manage',
            'event' => 'stickies.note.deleted',
        ],
        [
            'method' => 'POST',
            'path' => '/projects/stickies/note/move',
            'permission' => 'stickies.manage',
            'event' => 'stickies.note.moved',
        ],
    ],
    'events' => [
        ['type' => 'stickies.note.saved', 'automatic' => false, 'note' => 'Sticky created or updated'],
        ['type' => 'stickies.note.deleted', 'automatic' => false, 'note' => 'Sticky removed'],
        ['type' => 'stickies.note.moved', 'automatic' => false, 'note' => 'Sticky repositioned on board'],
    ],
];
