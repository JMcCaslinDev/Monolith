<?php

declare(strict_types=1);

namespace CursorShare;

use PDO;
use RuntimeException;

/** CRUD, voting, and stats for community Cursor asset posts. */
final class ShareService
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @param list<string> $tags */
    /** @return array<string, mixed> */
    public function create(
        int $userId,
        string $category,
        string $title,
        string $description,
        string $filename,
        ?string $version,
        string $content,
        array $tags = [],
    ): array {
        $check = ContentGuard::validatePost($category, $title, $description, $filename, $content);
        if (!$check['ok']) {
            throw new RuntimeException($check['error'] ?? 'Invalid post');
        }

        $version = self::normalizeVersion($version);
        $tagsJson = self::encodeTags($tags);

        $stmt = $this->db->prepare(
            'INSERT INTO cursor_share_posts
                (user_id, category, title, description, filename, version, content, tags)
             VALUES
                (:uid, :cat, :title, :desc, :file, :ver, :content, :tags)'
        );
        $stmt->execute([
            'uid' => $userId,
            'cat' => $category,
            'title' => $check['title'],
            'desc' => $check['description'] !== '' ? $check['description'] : null,
            'file' => $check['filename'],
            'ver' => $version,
            'content' => $check['content'],
            'tags' => $tagsJson,
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
        string $description,
        string $filename,
        ?string $version,
        string $content,
        array $tags = [],
    ): array {
        $existing = $this->findForOwner($userId, $postId);
        if ($existing === null) {
            throw new RuntimeException('Post not found or not yours');
        }

        $check = ContentGuard::validatePost(
            (string) $existing['category'],
            $title,
            $description,
            $filename,
            $content,
        );
        if (!$check['ok']) {
            throw new RuntimeException($check['error'] ?? 'Invalid post');
        }

        $version = self::normalizeVersion($version);
        $tagsJson = self::encodeTags($tags);

        $stmt = $this->db->prepare(
            'UPDATE cursor_share_posts
             SET title = :title, description = :desc, filename = :file, version = :ver,
                 content = :content, tags = :tags, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND user_id = :uid'
        );
        $stmt->execute([
            'title' => $check['title'],
            'desc' => $check['description'] !== '' ? $check['description'] : null,
            'file' => $check['filename'],
            'ver' => $version,
            'content' => $check['content'],
            'tags' => $tagsJson,
            'id' => $postId,
            'uid' => $userId,
        ]);

        $row = $this->find($postId);
        if ($row === null) {
            throw new RuntimeException('Failed to update post');
        }

        return $row;
    }

    /** @return array<string, mixed>|null */
    public function find(int $postId, bool $withContent = false): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, u.name AS author_name, u.email AS author_email
             FROM cursor_share_posts p
             JOIN users u ON u.id = p.user_id
             WHERE p.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $postId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $this->hydrateRow($row, includeContent: $withContent) : null;
    }

    /** @return array<string, mixed>|null */
    public function findForOwner(int $userId, int $postId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, u.name AS author_name, u.email AS author_email
             FROM cursor_share_posts p
             JOIN users u ON u.id = p.user_id
             WHERE p.id = :id AND p.user_id = :uid
             LIMIT 1'
        );
        $stmt->execute(['id' => $postId, 'uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $this->hydrateRow($row, includeContent: true) : null;
    }

    /**
     * @param array{
     *   category?: string,
     *   q?: string,
     *   user_id?: int,
     *   mine?: bool,
     *   date_from?: string,
     *   date_to?: string,
     *   sort?: string,
     *   limit?: int,
     *   offset?: int
     * } $filters
     * @return list<array<string, mixed>>
     */
    public function list(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['category']) && Catalog::isCategory((string) $filters['category'])) {
            $where[] = 'p.category = :cat';
            $params['cat'] = $filters['category'];
        }

        if (!empty($filters['mine']) && !empty($filters['user_id'])) {
            $where[] = 'p.user_id = :uid';
            $params['uid'] = (int) $filters['user_id'];
        } elseif (!empty($filters['user_id'])) {
            $where[] = 'p.user_id = :uid';
            $params['uid'] = (int) $filters['user_id'];
        }

        if (!empty($filters['q'])) {
            $where[] = '(p.title LIKE :q OR p.description LIKE :q OR p.filename LIKE :q)';
            $params['q'] = '%' . self::escapeLike((string) $filters['q']) . '%';
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'p.created_at >= :from';
            $params['from'] = (string) $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'p.created_at <= :to';
            $params['to'] = (string) $filters['date_to'] . ' 23:59:59';
        }

        $sort = match ($filters['sort'] ?? 'popular') {
            'newest' => 'p.created_at DESC',
            'views' => 'p.views DESC, p.created_at DESC',
            'downloads' => 'p.downloads DESC, p.created_at DESC',
            default => '(p.upvotes - p.downvotes) DESC, p.upvotes DESC, p.views DESC',
        };

        $limit = max(1, min(100, (int) ($filters['limit'] ?? 50)));
        $offset = max(0, (int) ($filters['offset'] ?? 0));

        $sql = 'SELECT p.id, p.user_id, p.category, p.title, p.description, p.filename, p.version,
                       p.tags, p.upvotes, p.downvotes, p.views, p.downloads, p.created_at, p.updated_at,
                       u.name AS author_name, u.email AS author_email
                FROM cursor_share_posts p
                JOIN users u ON u.id = p.user_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY ' . $sort . '
                LIMIT ' . $limit . ' OFFSET ' . $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array_map(fn (array $row) => $this->hydrateRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    public function topForCategory(string $category, int $limit = 10): array
    {
        if (!Catalog::isCategory($category)) {
            return [];
        }

        return $this->list([
            'category' => $category,
            'sort' => 'popular',
            'limit' => max(1, min(10, $limit)),
        ]);
    }

    /** @return array<string, list<array<string, mixed>>> */
    public function topByCategory(int $limit = 10): array
    {
        $out = [];
        foreach (Catalog::CATEGORIES as $category) {
            $out[$category] = $this->topForCategory($category, $limit);
        }

        return $out;
    }

    /** @return array{vote: int, upvotes: int, downvotes: int, score: int} */
    public function vote(int $userId, int $postId, int $direction): array
    {
        if (!in_array($direction, [1, -1], true)) {
            throw new RuntimeException('Invalid vote');
        }

        if ($this->find($postId) === null) {
            throw new RuntimeException('Post not found');
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'SELECT vote FROM cursor_share_votes WHERE post_id = :pid AND user_id = :uid'
            );
            $stmt->execute(['pid' => $postId, 'uid' => $userId]);
            $existing = $stmt->fetchColumn();

            if ($existing !== false && (int) $existing === $direction) {
                $this->db->prepare(
                    'DELETE FROM cursor_share_votes WHERE post_id = :pid AND user_id = :uid'
                )->execute(['pid' => $postId, 'uid' => $userId]);
                $this->applyVoteDelta($postId, $direction, remove: true);
                $finalVote = 0;
            } elseif ($existing !== false) {
                $this->db->prepare(
                    'UPDATE cursor_share_votes SET vote = :vote WHERE post_id = :pid AND user_id = :uid'
                )->execute(['vote' => $direction, 'pid' => $postId, 'uid' => $userId]);
                $this->applyVoteDelta($postId, (int) $existing, remove: true);
                $this->applyVoteDelta($postId, $direction, remove: false);
                $finalVote = $direction;
            } else {
                $this->db->prepare(
                    'INSERT INTO cursor_share_votes (post_id, user_id, vote) VALUES (:pid, :uid, :vote)'
                )->execute(['pid' => $postId, 'uid' => $userId, 'vote' => $direction]);
                $this->applyVoteDelta($postId, $direction, remove: false);
                $finalVote = $direction;
            }

            $counts = $this->voteCounts($postId);
            $this->db->commit();

            return [
                'vote' => $finalVote,
                'upvotes' => $counts['upvotes'],
                'downvotes' => $counts['downvotes'],
                'score' => $counts['upvotes'] - $counts['downvotes'],
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function userVote(int $userId, int $postId): int
    {
        $stmt = $this->db->prepare(
            'SELECT vote FROM cursor_share_votes WHERE post_id = :pid AND user_id = :uid'
        );
        $stmt->execute(['pid' => $postId, 'uid' => $userId]);
        $vote = $stmt->fetchColumn();

        return $vote === false ? 0 : (int) $vote;
    }

    public function recordView(int $postId): void
    {
        $this->db->prepare(
            'UPDATE cursor_share_posts SET views = views + 1 WHERE id = :id'
        )->execute(['id' => $postId]);
    }

    public function recordDownload(int $postId): void
    {
        $this->db->prepare(
            'UPDATE cursor_share_posts SET downloads = downloads + 1 WHERE id = :id'
        )->execute(['id' => $postId]);
    }

    /** @return array{upvotes: int, downvotes: int} */
    private function voteCounts(int $postId): array
    {
        $stmt = $this->db->prepare(
            'SELECT upvotes, downvotes FROM cursor_share_posts WHERE id = :id'
        );
        $stmt->execute(['id' => $postId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'upvotes' => (int) ($row['upvotes'] ?? 0),
            'downvotes' => (int) ($row['downvotes'] ?? 0),
        ];
    }

    private function applyVoteDelta(int $postId, int $direction, bool $remove): void
    {
        $column = $direction === 1 ? 'upvotes' : 'downvotes';
        $op = $remove ? '-' : '+';
        $this->db->exec(
            'UPDATE cursor_share_posts SET ' . $column . ' = ' . $column . ' ' . $op . ' 1 WHERE id = ' . (int) $postId
        );
    }

    /** @param array<string, mixed> $row */
    /** @return array<string, mixed> */
    private function hydrateRow(array $row, bool $includeContent = false): array
    {
        $row['id'] = (int) $row['id'];
        $row['user_id'] = (int) $row['user_id'];
        $row['upvotes'] = (int) $row['upvotes'];
        $row['downvotes'] = (int) $row['downvotes'];
        $row['views'] = (int) $row['views'];
        $row['downloads'] = (int) $row['downloads'];
        $row['score'] = $row['upvotes'] - $row['downvotes'];
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

    private static function normalizeVersion(?string $version): ?string
    {
        $version = trim((string) $version);
        if ($version === '') {
            return null;
        }
        if (strlen($version) > 64) {
            $version = substr($version, 0, 64);
        }

        return $version;
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['%', '_'], ['\\%', '\\_'], $value);
    }
}
