<?php

declare(strict_types=1);

return [
    'id' => 'blog',
    'project' => [
        'name' => 'Blog',
        'description' => 'Write, publish, and track blog posts with SEO.',
        'icon' => '✎',
        'path' => '/projects/blog',
        'permissions' => [
            'view' => 'projects.blog.view',
            'open' => 'projects.blog.open',
        ],
    ],
    'permissions' => [
        ['name' => 'projects.blog.view', 'description' => 'See Blog on dashboard', 'category' => 'projects'],
        ['name' => 'projects.blog.open', 'description' => 'Open Blog project', 'category' => 'projects'],
        ['name' => 'blog.posts.view', 'description' => 'View blog drafts and published posts', 'category' => 'blog'],
        ['name' => 'blog.posts.manage', 'description' => 'Create, edit, publish, and delete blog posts', 'category' => 'blog'],
        ['name' => 'blog.analytics.view', 'description' => 'View blog post analytics', 'category' => 'blog'],
    ],
    'routes' => [
        ['method' => 'GET', 'path' => '/projects/blog', 'permission' => 'projects.blog.open', 'event' => 'page.viewed'],
        ['method' => 'GET', 'path' => '/projects/blog/api/state', 'permission' => 'blog.posts.view', 'event' => 'page.viewed', 'note' => 'Editor list, post detail, analytics'],
        ['method' => 'GET', 'path' => '/blog', 'permission' => null, 'event' => 'blog.index.viewed', 'note' => 'Public published index'],
        ['method' => 'GET', 'path' => '/blog/post', 'permission' => null, 'event' => 'blog.post.viewed', 'note' => 'Public post via slug in path (index.php)'],
        ['method' => 'GET', 'path' => '/blog/sitemap.xml', 'permission' => null, 'event' => 'page.viewed', 'note' => 'XML sitemap for published posts'],
    ],
    'mutations' => [
        [
            'method' => 'POST',
            'path' => '/projects/blog/posts/create',
            'permission' => 'blog.posts.manage',
            'event' => 'blog.post.created',
        ],
        [
            'method' => 'POST',
            'path' => '/projects/blog/posts/update',
            'permission' => 'blog.posts.manage',
            'event' => 'blog.post.updated',
        ],
        [
            'method' => 'POST',
            'path' => '/projects/blog/posts/publish',
            'permission' => 'blog.posts.manage',
            'event' => 'blog.post.published',
        ],
        [
            'method' => 'POST',
            'path' => '/projects/blog/posts/unpublish',
            'permission' => 'blog.posts.manage',
            'event' => 'blog.post.unpublished',
        ],
        [
            'method' => 'POST',
            'path' => '/projects/blog/posts/delete',
            'permission' => 'blog.posts.manage',
            'event' => 'blog.post.deleted',
        ],
        [
            'method' => 'POST',
            'path' => '/projects/blog/upload',
            'permission' => 'blog.posts.manage',
            'event' => 'blog.image.uploaded',
        ],
    ],
    'events' => [
        ['type' => 'blog.post.created', 'automatic' => false, 'note' => 'Blog draft created'],
        ['type' => 'blog.post.updated', 'automatic' => false, 'note' => 'Blog post saved'],
        ['type' => 'blog.post.published', 'automatic' => false, 'note' => 'Blog post published'],
        ['type' => 'blog.post.unpublished', 'automatic' => false, 'note' => 'Blog post reverted to draft'],
        ['type' => 'blog.post.deleted', 'automatic' => false, 'note' => 'Blog post deleted'],
        ['type' => 'blog.post.viewed', 'automatic' => false, 'note' => 'Public blog post viewed'],
        ['type' => 'blog.index.viewed', 'automatic' => false, 'note' => 'Public blog index viewed'],
        ['type' => 'blog.image.uploaded', 'automatic' => false, 'note' => 'Blog image uploaded for post content'],
    ],
];
