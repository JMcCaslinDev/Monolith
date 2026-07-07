<?php

declare(strict_types=1);

return [
    'id' => 'cursor-share',
    'project' => [
        'name' => 'Cursor Share',
        'description' => 'Community rules, skills, commands, and hooks for Cursor.',
        'icon' => '◇',
        'path' => '/projects/cursor-share',
        'permissions' => [
            'view' => 'projects.cursor-share.view',
            'open' => 'projects.cursor-share.open',
        ],
    ],
    'permissions' => [
        ['name' => 'projects.cursor-share.view', 'description' => 'See Cursor Share on dashboard', 'category' => 'projects'],
        ['name' => 'projects.cursor-share.open', 'description' => 'Open Cursor Share project', 'category' => 'projects'],
        ['name' => 'cursor-share.browse', 'description' => 'Browse community Cursor assets', 'category' => 'cursor-share'],
        ['name' => 'cursor-share.post', 'description' => 'Create and edit own Cursor asset posts', 'category' => 'cursor-share'],
        ['name' => 'cursor-share.vote', 'description' => 'Upvote and downvote community posts', 'category' => 'cursor-share'],
        ['name' => 'cursor-share.download', 'description' => 'Download community Cursor assets', 'category' => 'cursor-share'],
    ],
    'routes' => [
        ['method' => 'GET', 'path' => '/projects/cursor-share', 'permission' => 'projects.cursor-share.open', 'event' => 'page.viewed'],
        ['method' => 'GET', 'path' => '/projects/cursor-share/api/state', 'permission' => 'cursor-share.browse', 'event' => 'page.viewed', 'note' => 'Poll posts, top 10, filters'],
        ['method' => 'GET', 'path' => '/projects/cursor-share/download', 'permission' => 'cursor-share.download', 'event' => 'cursor-share.post.downloaded'],
    ],
    'mutations' => [
        [
            'method' => 'POST',
            'path' => '/projects/cursor-share/posts/create',
            'permission' => 'cursor-share.post',
            'event' => 'cursor-share.post.created',
        ],
        [
            'method' => 'POST',
            'path' => '/projects/cursor-share/posts/update',
            'permission' => 'cursor-share.post',
            'event' => 'cursor-share.post.updated',
        ],
        [
            'method' => 'POST',
            'path' => '/projects/cursor-share/posts/vote',
            'permission' => 'cursor-share.vote',
            'event' => 'cursor-share.post.voted',
        ],
        [
            'method' => 'POST',
            'path' => '/projects/cursor-share/posts/view',
            'permission' => 'cursor-share.browse',
            'event' => 'cursor-share.post.viewed',
        ],
    ],
    'events' => [
        ['type' => 'cursor-share.post.created', 'automatic' => false, 'note' => 'User published a Cursor asset'],
        ['type' => 'cursor-share.post.updated', 'automatic' => false, 'note' => 'Poster edited their asset'],
        ['type' => 'cursor-share.post.viewed', 'automatic' => false, 'note' => 'User viewed a post detail'],
        ['type' => 'cursor-share.post.downloaded', 'automatic' => false, 'note' => 'User downloaded an asset file'],
        ['type' => 'cursor-share.post.voted', 'automatic' => false, 'note' => 'User voted on a post'],
    ],
];
