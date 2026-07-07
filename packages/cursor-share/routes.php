<?php

declare(strict_types=1);

require_once __DIR__ . '/src/Catalog.php';
require_once __DIR__ . '/src/ContentGuard.php';
require_once __DIR__ . '/src/ShareService.php';

use CursorShare\Catalog;

$jsonResponse = function (array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_THROW_ON_ERROR);
    exit;
};

$readContent = function (): string {
    if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
        $raw = file_get_contents($_FILES['file']['tmp_name']);
        return is_string($raw) ? $raw : '';
    }
    return trim((string) ($_POST['content'] ?? ''));
};

$parseTags = function (): array {
    $raw = trim((string) ($_POST['tags'] ?? ''));
    if ($raw === '') {
        return [];
    }
    $parts = preg_split('/[\s,]+/', $raw) ?: [];

    return array_values(array_filter(array_map('trim', $parts)));
};

$publicPost = function (array $post, int $userVote = 0, bool $withContent = false): array {
    $out = [
        'id' => (int) $post['id'],
        'user_id' => (int) $post['user_id'],
        'category' => $post['category'],
        'title' => $post['title'],
        'description' => $post['description'],
        'filename' => $post['filename'],
        'version' => $post['version'],
        'tags' => $post['tags'] ?? [],
        'upvotes' => (int) $post['upvotes'],
        'downvotes' => (int) $post['downvotes'],
        'score' => (int) ($post['score'] ?? ((int) $post['upvotes'] - (int) $post['downvotes'])),
        'views' => (int) $post['views'],
        'downloads' => (int) $post['downloads'],
        'created_at' => $post['created_at'],
        'updated_at' => $post['updated_at'],
        'author_name' => $post['author_name'] ?? null,
        'author_email' => $post['author_email'] ?? null,
        'user_vote' => $userVote,
    ];
    if ($withContent && isset($post['content'])) {
        $out['content'] = $post['content'];
    }

    return $out;
};

return [
    'GET /projects/cursor-share' => fn () => dispatch(function (): void {
        $user = auth()->currentUser();
        $uid = (int) $user['id'];
        events()->record('project.opened', $uid, 'project', 'cursor-share', ['project' => 'cursor-share']);

        package_view('cursor-share', 'app', [
            'title' => 'Cursor Share',
            'fullWidth' => true,
            'categories' => Catalog::all(),
            'csrf' => csrf_token(),
        ]);
    }, ['auth', 'perm:projects.cursor-share.open']),

    'GET /projects/cursor-share/api/state' => fn () => dispatch(function () use ($jsonResponse, $publicPost): void {
        $user = auth()->currentUser();
        $uid = (int) $user['id'];
        $perms = permissions()->allForUser($uid);

        $postId = (int) ($_GET['post_id'] ?? 0);
        $filters = [
            'category' => trim((string) ($_GET['category'] ?? '')),
            'q' => trim((string) ($_GET['q'] ?? '')),
            'sort' => trim((string) ($_GET['sort'] ?? 'popular')),
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to' => trim((string) ($_GET['date_to'] ?? '')),
            'limit' => (int) ($_GET['limit'] ?? 50),
            'offset' => (int) ($_GET['offset'] ?? 0),
        ];

        if (($_GET['mine'] ?? '') === '1') {
            $filters['mine'] = true;
            $filters['user_id'] = $uid;
        }

        $posts = cursor_share()->list($filters);
        $postsOut = [];
        foreach ($posts as $post) {
            $vote = in_array('cursor-share.vote', $perms, true)
                ? cursor_share()->userVote($uid, (int) $post['id'])
                : 0;
            $postsOut[] = $publicPost($post, $vote);
        }

        $top = cursor_share()->topByCategory(10);
        $topOut = [];
        foreach ($top as $cat => $items) {
            $topOut[$cat] = array_map(
                fn (array $p) => $publicPost(
                    $p,
                    in_array('cursor-share.vote', $perms, true)
                        ? cursor_share()->userVote($uid, (int) $p['id'])
                        : 0
                ),
                $items
            );
        }

        $selected = null;
        if ($postId > 0) {
            $row = cursor_share()->find($postId, withContent: true);
            if ($row !== null) {
                $vote = in_array('cursor-share.vote', $perms, true)
                    ? cursor_share()->userVote($uid, $postId)
                    : 0;
                $selected = $publicPost($row, $vote, withContent: true);
            }
        }

        $jsonResponse([
            'posts' => $postsOut,
            'top' => $topOut,
            'selected' => $selected,
            'categories' => Catalog::all(),
            'canPost' => in_array('cursor-share.post', $perms, true),
            'canVote' => in_array('cursor-share.vote', $perms, true),
            'canDownload' => in_array('cursor-share.download', $perms, true),
            'currentUserId' => $uid,
        ]);
    }, ['auth', 'perm:cursor-share.browse'], recordPageView: false),

    'GET /projects/cursor-share/download' => fn () => dispatch(function (): void {
        $user = auth()->currentUser();
        $uid = (int) $user['id'];
        $postId = (int) ($_GET['post_id'] ?? 0);
        if ($postId < 1) {
            http_response_code(400);
            exit('Invalid post');
        }

        $row = cursor_share()->find($postId, withContent: true);
        if ($row === null) {
            http_response_code(404);
            exit('Not found');
        }

        cursor_share()->recordDownload($postId);
        events()->record('cursor-share.post.downloaded', $uid, 'cursor_share_post', (string) $postId, [
            'category' => $row['category'],
            'filename' => $row['filename'],
            'title' => $row['title'],
        ]);

        $mime = match ($row['category']) {
            'hooks' => 'application/json',
            'rules' => 'text/markdown',
            default => 'text/plain',
        };

        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . basename((string) $row['filename']) . '"');
        header('X-Content-Type-Options: nosniff');
        echo (string) $row['content'];
        exit;
    }, ['auth', 'perm:cursor-share.download'], recordPageView: false),

    'POST /projects/cursor-share/posts/create' => fn () => dispatch(function () use ($jsonResponse, $readContent, $parseTags, $publicPost): void {
        verify_csrf();
        $user = auth()->currentUser();
        $uid = (int) $user['id'];

        $category = trim((string) ($_POST['category'] ?? ''));
        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $filename = trim((string) ($_POST['filename'] ?? ''));
        $version = trim((string) ($_POST['version'] ?? ''));
        $content = $readContent();
        $tags = $parseTags();

        try {
            $post = cursor_share()->create($uid, $category, $title, $description, $filename, $version, $content, $tags);
        } catch (Throwable $e) {
            $jsonResponse(['error' => $e->getMessage()], 400);
        }

        events()->record('cursor-share.post.created', $uid, 'cursor_share_post', (string) $post['id'], [
            'category' => $post['category'],
            'title' => $post['title'],
            'filename' => $post['filename'],
            'version' => $post['version'],
        ]);

        $jsonResponse(['post' => $publicPost($post)]);
    }, ['auth', 'perm:cursor-share.post'], recordPageView: false),

    'POST /projects/cursor-share/posts/update' => fn () => dispatch(function () use ($jsonResponse, $readContent, $parseTags, $publicPost): void {
        verify_csrf();
        $user = auth()->currentUser();
        $uid = (int) $user['id'];
        $postId = (int) ($_POST['post_id'] ?? 0);
        if ($postId < 1) {
            $jsonResponse(['error' => 'Invalid post'], 400);
        }

        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $filename = trim((string) ($_POST['filename'] ?? ''));
        $version = trim((string) ($_POST['version'] ?? ''));
        $content = $readContent();
        $tags = $parseTags();

        try {
            $post = cursor_share()->update($uid, $postId, $title, $description, $filename, $version, $content, $tags);
        } catch (Throwable $e) {
            $jsonResponse(['error' => $e->getMessage()], 400);
        }

        events()->record('cursor-share.post.updated', $uid, 'cursor_share_post', (string) $postId, [
            'category' => $post['category'],
            'title' => $post['title'],
            'filename' => $post['filename'],
        ]);

        $jsonResponse(['post' => $publicPost($post)]);
    }, ['auth', 'perm:cursor-share.post'], recordPageView: false),

    'POST /projects/cursor-share/posts/vote' => fn () => dispatch(function () use ($jsonResponse): void {
        verify_csrf();
        $user = auth()->currentUser();
        $uid = (int) $user['id'];
        $postId = (int) ($_POST['post_id'] ?? 0);
        $direction = (int) ($_POST['direction'] ?? 0);
        if ($postId < 1) {
            $jsonResponse(['error' => 'Invalid post'], 400);
        }

        try {
            $result = cursor_share()->vote($uid, $postId, $direction);
        } catch (Throwable $e) {
            $jsonResponse(['error' => $e->getMessage()], 400);
        }

        events()->record('cursor-share.post.voted', $uid, 'cursor_share_post', (string) $postId, [
            'direction' => $result['vote'],
            'score' => $result['score'],
        ]);

        $jsonResponse($result);
    }, ['auth', 'perm:cursor-share.vote'], recordPageView: false),

    'POST /projects/cursor-share/posts/view' => fn () => dispatch(function () use ($jsonResponse): void {
        verify_csrf();
        $user = auth()->currentUser();
        $uid = (int) $user['id'];
        $postId = (int) ($_POST['post_id'] ?? 0);
        if ($postId < 1) {
            $jsonResponse(['error' => 'Invalid post'], 400);
        }

        if (cursor_share()->find($postId) === null) {
            $jsonResponse(['error' => 'Not found'], 404);
        }

        cursor_share()->recordView($postId);
        events()->record('cursor-share.post.viewed', $uid, 'cursor_share_post', (string) $postId, []);

        $jsonResponse(['ok' => true]);
    }, ['auth', 'perm:cursor-share.browse'], recordPageView: false),
];
