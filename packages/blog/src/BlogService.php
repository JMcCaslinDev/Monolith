<?php

declare(strict_types=1);

namespace Blog;

use PDO;
use RuntimeException;

/** CRUD, publish workflow, views, and analytics for blog posts. */
final class BlogService
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @param list<string> $tags */
    /** @return array<string, mixed> */
    public function create(
        int $userId,
        string $title,
        ?string $slug,
        string $excerpt,
        string $content,
        array $tags = [],
        ?string $metaTitle = null,
        ?string $metaDescription = null,
        ?string $ogImageUrl = null,
    ): array {
        $check = self::validatePost($title, $slug, $excerpt, $content, $metaTitle, $metaDescription, $ogImageUrl);
        if (!$check['ok']) {
            throw new RuntimeException($check['error'] ?? 'Invalid post');
        }

        $slug = $this->ensureUniqueSlug($check['slug']);

        $stmt = $this->db->prepare(
            'INSERT INTO blog_posts
                (user_id, slug, title, excerpt, content, status, tags, meta_title, meta_description, og_image_url)
             VALUES
                (:uid, :slug, :title, :excerpt, :content, :status, :tags, :meta_title, :meta_desc, :og_image)'
        );
        $stmt->execute([
            'uid' => $userId,
            'slug' => $slug,
            'title' => $check['title'],
            'excerpt' => $check['excerpt'] !== '' ? $check['excerpt'] : null,
            'content' => $check['content'],
            'status' => 'draft',
            'tags' => self::encodeTags($tags),
            'meta_title' => $check['meta_title'],
            'meta_desc' => $check['meta_description'],
            'og_image' => $check['og_image_url'],
        ]);

        $id = (int) $this->db->lastInsertId();
        $row = $this->find($id);
        if ($row === null) {
            throw new RuntimeException('Failed to create post');
        }

        return $row;
    }

    /** @param list<string> $tags */
    /** @return array<string, mixed> */
    public function update(
        int $userId,
        int $postId,
        string $title,
        ?string $slug,
        string $excerpt,
        string $content,
        array $tags = [],
        ?string $metaTitle = null,
        ?string $metaDescription = null,
        ?string $ogImageUrl = null,
    ): array {
        $existing = $this->find($postId);
        if ($existing === null) {
            throw new RuntimeException('Post not found');
        }

        $check = self::validatePost($title, $slug, $excerpt, $content, $metaTitle, $metaDescription, $ogImageUrl);
        if (!$check['ok']) {
            throw new RuntimeException($check['error'] ?? 'Invalid post');
        }

        $slug = $check['slug'];
        if ($slug !== $existing['slug']) {
            $slug = $this->ensureUniqueSlug($slug, $postId);
        }

        $stmt = $this->db->prepare(
            'UPDATE blog_posts
             SET slug = :slug, title = :title, excerpt = :excerpt, content = :content,
                 tags = :tags, meta_title = :meta_title, meta_description = :meta_desc,
                 og_image_url = :og_image, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->execute([
            'slug' => $slug,
            'title' => $check['title'],
            'excerpt' => $check['excerpt'] !== '' ? $check['excerpt'] : null,
            'content' => $check['content'],
            'tags' => self::encodeTags($tags),
            'meta_title' => $check['meta_title'],
            'meta_desc' => $check['meta_description'],
            'og_image' => $check['og_image_url'],
            'id' => $postId,
        ]);

        $row = $this->find($postId, withContent: true);
        if ($row === null) {
            throw new RuntimeException('Failed to update post');
        }

        return $row;
    }

    /** @return array<string, mixed> */
    public function publish(int $postId): array
    {
        $existing = $this->find($postId);
        if ($existing === null) {
            throw new RuntimeException('Post not found');
        }
        if ($existing['status'] === 'published') {
            return $existing;
        }

        $this->db->prepare(
            'UPDATE blog_posts
             SET status = :status, published_at = COALESCE(published_at, CURRENT_TIMESTAMP),
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        )->execute(['status' => 'published', 'id' => $postId]);

        $row = $this->find($postId);
        if ($row === null) {
            throw new RuntimeException('Failed to publish post');
        }

        return $row;
    }

    /** @return array<string, mixed> */
    public function unpublish(int $postId): array
    {
        $existing = $this->find($postId);
        if ($existing === null) {
            throw new RuntimeException('Post not found');
        }

        $this->db->prepare(
            'UPDATE blog_posts SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        )->execute(['status' => 'draft', 'id' => $postId]);

        $row = $this->find($postId);
        if ($row === null) {
            throw new RuntimeException('Failed to unpublish post');
        }

        return $row;
    }

    public function delete(int $postId): void
    {
        if ($this->find($postId) === null) {
            throw new RuntimeException('Post not found');
        }

        $this->db->prepare('DELETE FROM blog_posts WHERE id = :id')->execute(['id' => $postId]);
    }

    /** @return array<string, mixed>|null */
    public function find(int $postId, bool $withContent = false): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, u.name AS author_name, u.email AS author_email
             FROM blog_posts p
             JOIN users u ON u.id = p.user_id
             WHERE p.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $postId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $this->hydrateRow($row, includeContent: $withContent) : null;
    }

    /** @return array<string, mixed>|null */
    public function findPublishedBySlug(string $slug, bool $withContent = true): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, u.name AS author_name, u.email AS author_email
             FROM blog_posts p
             JOIN users u ON u.id = p.user_id
             WHERE p.slug = :slug AND p.status = :status
             LIMIT 1'
        );
        $stmt->execute(['slug' => $slug, 'status' => 'published']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $this->hydrateRow($row, includeContent: $withContent) : null;
    }

    /**
     * @param array{
     *   status?: string,
     *   q?: string,
     *   limit?: int,
     *   offset?: int
     * } $filters
     * @return list<array<string, mixed>>
     */
    public function list(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['status']) && in_array($filters['status'], ['draft', 'published'], true)) {
            $where[] = 'p.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['q'])) {
            $where[] = '(p.title LIKE :q OR p.excerpt LIKE :q OR p.slug LIKE :q)';
            $params['q'] = '%' . self::escapeLike((string) $filters['q']) . '%';
        }

        $limit = max(1, min(100, (int) ($filters['limit'] ?? 50)));
        $offset = max(0, (int) ($filters['offset'] ?? 0));

        $sql = 'SELECT p.id, p.user_id, p.slug, p.title, p.excerpt, p.status, p.tags,
                       p.meta_title, p.meta_description, p.og_image_url, p.views,
                       p.published_at, p.created_at, p.updated_at,
                       u.name AS author_name, u.email AS author_email
                FROM blog_posts p
                JOIN users u ON u.id = p.user_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY COALESCE(p.published_at, p.created_at) DESC
                LIMIT ' . $limit . ' OFFSET ' . $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array_map(fn (array $row) => $this->hydrateRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    public function listPublished(int $limit = 50, int $offset = 0): array
    {
        return $this->list(['status' => 'published', 'limit' => $limit, 'offset' => $offset]);
    }

    /** @return list<array{id: int, slug: string, title: string, updated_at: string}> */
    public function sitemapEntries(): array
    {
        $stmt = $this->db->query(
            'SELECT id, slug, title, updated_at FROM blog_posts
             WHERE status = \'published\'
             ORDER BY published_at DESC'
        );

        return array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'slug' => (string) $row['slug'],
                'title' => (string) $row['title'],
                'updated_at' => (string) $row['updated_at'],
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function recordView(int $postId): void
    {
        $this->db->prepare('UPDATE blog_posts SET views = views + 1 WHERE id = :id')
            ->execute(['id' => $postId]);

        $today = gmdate('Y-m-d');
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $this->db->prepare(
                'INSERT INTO blog_daily_views (post_id, view_date, views) VALUES (:pid, :d, 1)
                 ON CONFLICT(post_id, view_date) DO UPDATE SET views = views + 1'
            )->execute(['pid' => $postId, 'd' => $today]);
        } else {
            $this->db->prepare(
                'INSERT INTO blog_daily_views (post_id, view_date, views) VALUES (:pid, :d, 1)
                 ON DUPLICATE KEY UPDATE views = views + 1'
            )->execute(['pid' => $postId, 'd' => $today]);
        }
    }

    /**
     * @return array{
     *   totals: array{posts: int, published: int, drafts: int, views: int},
     *   top_posts: list<array<string, mixed>>,
     *   daily_views: list<array{date: string, views: int}>
     * }
     */
    public function analytics(int $days = 30): array
    {
        $days = max(7, min(90, $days));
        $since = gmdate('Y-m-d', strtotime('-' . ($days - 1) . ' days'));

        $totals = $this->db->query(
            'SELECT
                COUNT(*) AS posts,
                SUM(CASE WHEN status = \'published\' THEN 1 ELSE 0 END) AS published,
                SUM(CASE WHEN status = \'draft\' THEN 1 ELSE 0 END) AS drafts,
                COALESCE(SUM(views), 0) AS views
             FROM blog_posts'
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        $topStmt = $this->db->query(
            'SELECT id, slug, title, status, views, published_at
             FROM blog_posts
             ORDER BY views DESC, published_at DESC
             LIMIT 10'
        );
        $topPosts = array_map(function (array $row): array {
            $row['id'] = (int) $row['id'];
            $row['views'] = (int) $row['views'];

            return $row;
        }, $topStmt->fetchAll(PDO::FETCH_ASSOC));

        $dailyStmt = $this->db->prepare(
            'SELECT view_date AS date, SUM(views) AS views
             FROM blog_daily_views
             WHERE view_date >= :since
             GROUP BY view_date
             ORDER BY view_date ASC'
        );
        $dailyStmt->execute(['since' => $since]);
        $daily = [];
        foreach ($dailyStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $daily[] = ['date' => (string) $row['date'], 'views' => (int) $row['views']];
        }

        return [
            'totals' => [
                'posts' => (int) ($totals['posts'] ?? 0),
                'published' => (int) ($totals['published'] ?? 0),
                'drafts' => (int) ($totals['drafts'] ?? 0),
                'views' => (int) ($totals['views'] ?? 0),
            ],
            'top_posts' => $topPosts,
            'daily_views' => $daily,
        ];
    }

    /** @return array<string, mixed> */
    public function seoForPost(array $post): array
    {
        $title = trim((string) ($post['meta_title'] ?? ''));
        if ($title === '') {
            $title = (string) $post['title'];
        }

        $description = trim((string) ($post['meta_description'] ?? ''));
        if ($description === '' && !empty($post['excerpt'])) {
            $description = (string) $post['excerpt'];
        }

        return [
            'title' => $title,
            'description' => $description,
            'og_image' => $post['og_image_url'] ?? null,
            'tags' => $post['tags'] ?? [],
            'canonical_path' => '/blog/' . $post['slug'],
        ];
    }

    /**
     * @return array{
     *   ok: bool,
     *   error?: string,
     *   title: string,
     *   slug: string,
     *   excerpt: string,
     *   content: string,
     *   meta_title: ?string,
     *   meta_description: ?string,
     *   og_image_url: ?string
     * }
     */
    private static function validatePost(
        string $title,
        ?string $slug,
        string $excerpt,
        string $content,
        ?string $metaTitle,
        ?string $metaDescription,
        ?string $ogImageUrl,
    ): array {
        $title = trim($title);
        if ($title === '') {
            return ['ok' => false, 'error' => 'Title is required', 'title' => '', 'slug' => '', 'excerpt' => '', 'content' => '', 'meta_title' => null, 'meta_description' => null, 'og_image_url' => null];
        }
        if (strlen($title) > 200) {
            $title = substr($title, 0, 200);
        }

        $slug = trim((string) $slug);
        if ($slug === '') {
            $slug = Slug::fromTitle($title);
        }
        if (!Slug::isValid($slug)) {
            return ['ok' => false, 'error' => 'Invalid slug', 'title' => $title, 'slug' => '', 'excerpt' => '', 'content' => '', 'meta_title' => null, 'meta_description' => null, 'og_image_url' => null];
        }

        $excerpt = trim($excerpt);
        if (strlen($excerpt) > 500) {
            $excerpt = substr($excerpt, 0, 500);
        }

        $content = trim($content);
        if ($content === '') {
            return ['ok' => false, 'error' => 'Content is required', 'title' => $title, 'slug' => $slug, 'excerpt' => $excerpt, 'content' => '', 'meta_title' => null, 'meta_description' => null, 'og_image_url' => null];
        }
        if (strlen($content) > 200000) {
            return ['ok' => false, 'error' => 'Content is too long', 'title' => $title, 'slug' => $slug, 'excerpt' => $excerpt, 'content' => '', 'meta_title' => null, 'meta_description' => null, 'og_image_url' => null];
        }

        $metaTitle = self::normalizeOptional($metaTitle, 200);
        $metaDescription = self::normalizeOptional($metaDescription, 320);
        $ogImageUrl = self::normalizeOptional($ogImageUrl, 500);
        if ($ogImageUrl !== null && !self::isSafeUrl($ogImageUrl)) {
            return ['ok' => false, 'error' => 'Invalid OG image URL', 'title' => $title, 'slug' => $slug, 'excerpt' => $excerpt, 'content' => $content, 'meta_title' => $metaTitle, 'meta_description' => $metaDescription, 'og_image_url' => null];
        }

        return [
            'ok' => true,
            'title' => $title,
            'slug' => $slug,
            'excerpt' => $excerpt,
            'content' => $content,
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'og_image_url' => $ogImageUrl,
        ];
    }

    private function ensureUniqueSlug(string $slug, ?int $excludeId = null): string
    {
        $base = $slug;
        $n = 1;
        while ($this->slugTaken($slug, $excludeId)) {
            $suffix = '-' . $n;
            $slug = substr($base, 0, 120 - strlen($suffix)) . $suffix;
            $n++;
        }

        return $slug;
    }

    private function slugTaken(string $slug, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $stmt = $this->db->prepare('SELECT id FROM blog_posts WHERE slug = :slug AND id != :id LIMIT 1');
            $stmt->execute(['slug' => $slug, 'id' => $excludeId]);
        } else {
            $stmt = $this->db->prepare('SELECT id FROM blog_posts WHERE slug = :slug LIMIT 1');
            $stmt->execute(['slug' => $slug]);
        }

        return $stmt->fetchColumn() !== false;
    }

    /** @param array<string, mixed> $row */
    /** @return array<string, mixed> */
    private function hydrateRow(array $row, bool $includeContent = false): array
    {
        $row['id'] = (int) $row['id'];
        $row['user_id'] = (int) $row['user_id'];
        $row['views'] = (int) ($row['views'] ?? 0);
        $row['tags'] = self::decodeTags($row['tags'] ?? null);
        if (!$includeContent) {
            unset($row['content']);
        }

        return $row;
    }

    /** @param list<string> $tags */
    private static function encodeTags(array $tags): ?string
    {
        $clean = [];
        foreach ($tags as $tag) {
            $tag = trim(strtolower((string) $tag));
            if ($tag !== '' && strlen($tag) <= 32 && preg_match('/^[a-z0-9._-]+$/', $tag)) {
                $clean[] = $tag;
            }
        }
        $clean = array_values(array_unique($clean));
        if ($clean === []) {
            return null;
        }

        return json_encode($clean, JSON_THROW_ON_ERROR);
    }

    /** @return list<string> */
    private static function decodeTags(mixed $raw): array
    {
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }

    private static function normalizeOptional(?string $value, int $max): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (strlen($value) > $max) {
            $value = substr($value, 0, $max);
        }

        return $value;
    }

    private static function isSafeUrl(string $url): bool
    {
        if (str_starts_with($url, '/')) {
            return !str_contains($url, '..');
        }

        return (bool) preg_match('#^https?://#i', $url);
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['%', '_'], ['\\%', '\\_'], $value);
    }
}
