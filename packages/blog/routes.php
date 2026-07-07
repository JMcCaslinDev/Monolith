<?php

declare(strict_types=1);

require_once __DIR__ . '/src/Slug.php';
require_once __DIR__ . '/src/Markdown.php';
require_once __DIR__ . '/src/BlogService.php';

use Blog\Markdown;

$jsonResponse = function (array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_THROW_ON_ERROR);
    exit;
};

$parseTags = function (): array {
    $raw = trim((string) ($_POST['tags'] ?? ''));
    if ($raw === '') {
        return [];
    }
    $parts = preg_split('/[\s,]+/', $raw) ?: [];

    return array_values(array_filter(array_map('trim', $parts)));
};

$publicPost = function (array $post, bool $withContent = false, bool $withSeo = false): array {
    $out = [
        'id' => (int) $post['id'],
        'user_id' => (int) $post['user_id'],
        'slug' => $post['slug'],
        'title' => $post['title'],
        'excerpt' => $post['excerpt'],
        'status' => $post['status'],
        'tags' => $post['tags'] ?? [],
        'meta_title' => $post['meta_title'],
        'meta_description' => $post['meta_description'],
        'og_image_url' => $post['og_image_url'],
        'views' => (int) $post['views'],
        'published_at' => $post['published_at'],
        'created_at' => $post['created_at'],
        'updated_at' => $post['updated_at'],
        'author_name' => $post['author_name'] ?? null,
        'author_email' => $post['author_email'] ?? null,
        'public_url' => '/blog/' . $post['slug'],
    ];
    if ($withContent && isset($post['content'])) {
        $out['content'] = $post['content'];
        $out['content_html'] = Markdown::toHtml((string) $post['content']);
    }
    if ($withSeo) {
        $out['seo'] = blog()->seoForPost($post);
    }

    return $out;
};

$appUrl = fn (): string => rtrim((string) (config('app')['url'] ?? ''), '/');

return [
    'GET /projects/blog' => fn () => dispatch(function (): void {
        $user = auth()->currentUser();
        $uid = (int) $user['id'];
        $perms = permissions()->allForUser($uid);
        events()->record('project.opened', $uid, 'project', 'blog', ['project' => 'blog']);

        package_view('blog', 'app', [
            'title' => 'Blog',
            'fullWidth' => true,
            'csrf' => csrf_token(),
            'canManage' => in_array('blog.posts.manage', $perms, true),
            'canAnalytics' => in_array('blog.analytics.view', $perms, true),
        ]);
    }, ['auth', 'perm:projects.blog.open']),

    'GET /projects/blog/api/state' => fn () => dispatch(function () use ($jsonResponse, $publicPost): void {
        $user = auth()->currentUser();
        $uid = (int) $user['id'];
        $perms = permissions()->allForUser($uid);

        $postId = (int) ($_GET['post_id'] ?? 0);
        $status = trim((string) ($_GET['status'] ?? ''));
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'limit' => (int) ($_GET['limit'] ?? 50),
            'offset' => (int) ($_GET['offset'] ?? 0),
        ];
        if (in_array($status, ['draft', 'published'], true)) {
            $filters['status'] = $status;
        }

        $posts = blog()->list($filters);
        $postsOut = array_map(fn (array $p) => $publicPost($p, withSeo: true), $posts);

        $selected = null;
        if ($postId > 0) {
            $row = blog()->find($postId, withContent: true);
            if ($row !== null) {
                $selected = $publicPost($row, withContent: true, withSeo: true);
            }
        }

        $analytics = null;
        if (in_array('blog.analytics.view', $perms, true) && ($_GET['analytics'] ?? '') === '1') {
            $analytics = blog()->analytics((int) ($_GET['days'] ?? 30));
        }

        $jsonResponse([
            'posts' => $postsOut,
            'selected' => $selected,
            'analytics' => $analytics,
            'canManage' => in_array('blog.posts.manage', $perms, true),
            'canAnalytics' => in_array('blog.analytics.view', $perms, true),
            'currentUserId' => $uid,
        ]);
    }, ['auth', 'perm:blog.posts.view'], recordPageView: false),

    'POST /projects/blog/posts/create' => fn () => dispatch(function () use ($jsonResponse, $parseTags, $publicPost): void {
        verify_csrf();
        $user = auth()->currentUser();
        $uid = (int) $user['id'];

        $title = trim((string) ($_POST['title'] ?? ''));
        $slug = trim((string) ($_POST['slug'] ?? ''));
        $excerpt = trim((string) ($_POST['excerpt'] ?? ''));
        $content = (string) ($_POST['content'] ?? '');
        $tags = $parseTags();
        $metaTitle = trim((string) ($_POST['meta_title'] ?? ''));
        $metaDescription = trim((string) ($_POST['meta_description'] ?? ''));
        $ogImage = trim((string) ($_POST['og_image_url'] ?? ''));

        try {
            $post = blog()->create(
                $uid,
                $title,
                $slug !== '' ? $slug : null,
                $excerpt,
                $content,
                $tags,
                $metaTitle !== '' ? $metaTitle : null,
                $metaDescription !== '' ? $metaDescription : null,
                $ogImage !== '' ? $ogImage : null,
            );
        } catch (Throwable $e) {
            $jsonResponse(['error' => $e->getMessage()], 400);
        }

        events()->record('blog.post.created', $uid, 'blog_post', (string) $post['id'], [
            'title' => $post['title'],
            'slug' => $post['slug'],
            'status' => $post['status'],
        ]);

        $jsonResponse(['post' => $publicPost($post, withContent: true, withSeo: true)]);
    }, ['auth', 'perm:blog.posts.manage'], recordPageView: false),

    'POST /projects/blog/posts/update' => fn () => dispatch(function () use ($jsonResponse, $parseTags, $publicPost): void {
        verify_csrf();
        $user = auth()->currentUser();
        $uid = (int) $user['id'];
        $postId = (int) ($_POST['post_id'] ?? 0);
        if ($postId < 1) {
            $jsonResponse(['error' => 'Invalid post'], 400);
        }

        $title = trim((string) ($_POST['title'] ?? ''));
        $slug = trim((string) ($_POST['slug'] ?? ''));
        $excerpt = trim((string) ($_POST['excerpt'] ?? ''));
        $content = (string) ($_POST['content'] ?? '');
        $tags = $parseTags();
        $metaTitle = trim((string) ($_POST['meta_title'] ?? ''));
        $metaDescription = trim((string) ($_POST['meta_description'] ?? ''));
        $ogImage = trim((string) ($_POST['og_image_url'] ?? ''));

        try {
            $post = blog()->update(
                $uid,
                $postId,
                $title,
                $slug !== '' ? $slug : null,
                $excerpt,
                $content,
                $tags,
                $metaTitle !== '' ? $metaTitle : null,
                $metaDescription !== '' ? $metaDescription : null,
                $ogImage !== '' ? $ogImage : null,
            );
        } catch (Throwable $e) {
            $jsonResponse(['error' => $e->getMessage()], 400);
        }

        events()->record('blog.post.updated', $uid, 'blog_post', (string) $postId, [
            'title' => $post['title'],
            'slug' => $post['slug'],
            'status' => $post['status'],
        ]);

        $jsonResponse(['post' => $publicPost($post, withContent: true, withSeo: true)]);
    }, ['auth', 'perm:blog.posts.manage'], recordPageView: false),

    'POST /projects/blog/posts/publish' => fn () => dispatch(function () use ($jsonResponse, $publicPost): void {
        verify_csrf();
        $user = auth()->currentUser();
        $uid = (int) $user['id'];
        $postId = (int) ($_POST['post_id'] ?? 0);
        if ($postId < 1) {
            $jsonResponse(['error' => 'Invalid post'], 400);
        }

        try {
            $post = blog()->publish($postId);
        } catch (Throwable $e) {
            $jsonResponse(['error' => $e->getMessage()], 400);
        }

        events()->record('blog.post.published', $uid, 'blog_post', (string) $postId, [
            'title' => $post['title'],
            'slug' => $post['slug'],
        ]);

        $jsonResponse(['post' => $publicPost($post, withSeo: true)]);
    }, ['auth', 'perm:blog.posts.manage'], recordPageView: false),

    'POST /projects/blog/posts/unpublish' => fn () => dispatch(function () use ($jsonResponse, $publicPost): void {
        verify_csrf();
        $user = auth()->currentUser();
        $uid = (int) $user['id'];
        $postId = (int) ($_POST['post_id'] ?? 0);
        if ($postId < 1) {
            $jsonResponse(['error' => 'Invalid post'], 400);
        }

        try {
            $post = blog()->unpublish($postId);
        } catch (Throwable $e) {
            $jsonResponse(['error' => $e->getMessage()], 400);
        }

        events()->record('blog.post.unpublished', $uid, 'blog_post', (string) $postId, [
            'title' => $post['title'],
            'slug' => $post['slug'],
        ]);

        $jsonResponse(['post' => $publicPost($post, withSeo: true)]);
    }, ['auth', 'perm:blog.posts.manage'], recordPageView: false),

    'POST /projects/blog/posts/delete' => fn () => dispatch(function () use ($jsonResponse): void {
        verify_csrf();
        $user = auth()->currentUser();
        $uid = (int) $user['id'];
        $postId = (int) ($_POST['post_id'] ?? 0);
        if ($postId < 1) {
            $jsonResponse(['error' => 'Invalid post'], 400);
        }

        $existing = blog()->find($postId);
        if ($existing === null) {
            $jsonResponse(['error' => 'Not found'], 404);
        }

        try {
            blog()->delete($postId);
        } catch (Throwable $e) {
            $jsonResponse(['error' => $e->getMessage()], 400);
        }

        events()->record('blog.post.deleted', $uid, 'blog_post', (string) $postId, [
            'title' => $existing['title'],
            'slug' => $existing['slug'],
        ]);

        $jsonResponse(['ok' => true]);
    }, ['auth', 'perm:blog.posts.manage'], recordPageView: false),

    'POST /projects/blog/upload' => fn () => dispatch(function () use ($jsonResponse, $appUrl): void {
        verify_csrf();
        $user = auth()->currentUser();
        $uid = (int) $user['id'];

        if (empty($_FILES['image']['tmp_name']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
            $jsonResponse(['error' => 'No image uploaded'], 400);
        }

        $size = (int) ($_FILES['image']['size'] ?? 0);
        if ($size < 1 || $size > 5 * 1024 * 1024) {
            $jsonResponse(['error' => 'Image must be under 5 MB'], 400);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['image']['tmp_name']) ?: '';
        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => null,
        };
        if ($ext === null) {
            $jsonResponse(['error' => 'Unsupported image type'], 400);
        }

        $dir = dirname(__DIR__, 2) . '/public/uploads/blog';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            $jsonResponse(['error' => 'Upload directory unavailable'], 500);
        }

        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = $dir . '/' . $name;
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
            $jsonResponse(['error' => 'Upload failed'], 500);
        }

        $path = '/uploads/blog/' . $name;
        events()->record('blog.image.uploaded', $uid, 'blog_image', $name, [
            'path' => $path,
            'bytes' => $size,
        ]);

        $jsonResponse(['url' => $path, 'absolute_url' => $appUrl() . $path]);
    }, ['auth', 'perm:blog.posts.manage'], recordPageView: false),

    'GET /blog' => fn () => dispatch(function (): void {
        $posts = blog()->listPublished(50, 0);
        events()->record('blog.index.viewed', null, 'page', '/blog', ['page' => '/blog']);

        $data = [
            'title' => 'Blog',
            'metaDescription' => 'Latest articles and updates.',
            'canonicalUrl' => $appUrl() . '/blog',
            'posts' => $posts,
        ];
        extract($data, EXTR_SKIP);
        ob_start();
        require dirname(__DIR__, 2) . '/packages/blog/views/public_index.php';
        $content = ob_get_clean();
        require dirname(__DIR__, 2) . '/resources/views/layout.php';
    }, recordPageView: false),

    'GET /blog/post' => fn () => dispatch(function () use ($appUrl): void {
        $slug = trim((string) ($_GET['slug'] ?? ''));
        if ($slug === '') {
            http_response_code(404);
            view('errors/404', ['title' => 'Not found']);
            return;
        }

        $post = blog()->findPublishedBySlug($slug, withContent: true);
        if ($post === null) {
            http_response_code(404);
            view('errors/404', ['title' => 'Not found']);
            return;
        }

        blog()->recordView((int) $post['id']);
        events()->record('blog.post.viewed', null, 'blog_post', (string) $post['id'], [
            'slug' => $post['slug'],
            'title' => $post['title'],
        ]);

        $seo = blog()->seoForPost($post);
        $contentHtml = Markdown::toHtml((string) $post['content']);
        $canonical = $appUrl() . $seo['canonical_path'];

        $data = [
            'title' => $seo['title'],
            'metaDescription' => $seo['description'],
            'ogImage' => $seo['og_image'] ? (str_starts_with((string) $seo['og_image'], '/') ? $appUrl() . $seo['og_image'] : $seo['og_image']) : null,
            'canonicalUrl' => $canonical,
            'post' => $post,
            'contentHtml' => $contentHtml,
            'seo' => $seo,
        ];
        extract($data, EXTR_SKIP);
        ob_start();
        require dirname(__DIR__, 2) . '/packages/blog/views/public_post.php';
        $content = ob_get_clean();
        require dirname(__DIR__, 2) . '/resources/views/layout.php';
    }, recordPageView: false),

    'GET /blog/sitemap.xml' => fn () => dispatch(function () use ($appUrl): void {
        $entries = blog()->sitemapEntries();
        $base = $appUrl();

        header('Content-Type: application/xml; charset=UTF-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        echo '  <url><loc>' . htmlspecialchars($base . '/blog', ENT_XML1) . '</loc></url>' . "\n";
        foreach ($entries as $entry) {
            $loc = $base . '/blog/' . rawurlencode($entry['slug']);
            $lastmod = htmlspecialchars(substr((string) $entry['updated_at'], 0, 10), ENT_XML1);
            echo '  <url><loc>' . htmlspecialchars($loc, ENT_XML1) . '</loc><lastmod>' . $lastmod . '</lastmod></url>' . "\n";
        }
        echo '</urlset>';
        exit;
    }, recordPageView: false),
];
