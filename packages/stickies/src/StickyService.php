<?php

declare(strict_types=1);

namespace Stickies;

use PDO;
use RuntimeException;

/** Per-user sticky notes: board layout, categories, search. */
final class StickyService
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function listNotes(int $userId, ?string $search = null, ?string $category = null): array
    {
        $sql = 'SELECT * FROM stickies WHERE user_id = :uid';
        $params = ['uid' => $userId];

        if ($category !== null && $category !== '' && $category !== 'all') {
            $sql .= ' AND category = :cat';
            $params['cat'] = $category;
        }

        $sql .= ' ORDER BY section ASC, pos_y ASC, pos_x ASC, id ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll() ?: [];

        if ($search !== null && trim($search) !== '') {
            $rows = self::filterBySearch($rows, $search);
        }

        return array_map([$this, 'publicNote'], $rows);
    }

    /** @return list<string> */
    public function categoriesForUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT DISTINCT category FROM stickies WHERE user_id = :uid ORDER BY category ASC',
        );
        $stmt->execute(['uid' => $userId]);
        $cats = array_column($stmt->fetchAll() ?: [], 'category');

        return array_values(array_filter($cats, static fn (string $c): bool => $c !== ''));
    }

    /** @return list<string> */
    public function sectionsForUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT DISTINCT section FROM stickies WHERE user_id = :uid ORDER BY section ASC',
        );
        $stmt->execute(['uid' => $userId]);
        $sections = array_column($stmt->fetchAll() ?: [], 'section');

        return array_values(array_filter($sections, static fn (string $s): bool => $s !== ''));
    }

    /** @return array<string, mixed> */
    public function saveNote(
        int $userId,
        string $content,
        string $category,
        string $color,
        string $section,
        ?int $noteId = null,
        ?int $posX = null,
        ?int $posY = null,
    ): array {
        $category = self::normalizeLabel($category, 'general');
        $section = self::normalizeLabel($section, 'board');
        $color = StickyColors::isValid($color) ? $color : 'yellow';
        $content = trim($content);

        if ($noteId !== null && $noteId > 0) {
            $existing = $this->findRaw($userId, $noteId);
            if ($existing === null) {
                throw new RuntimeException('Sticky not found');
            }

            $x = $posX ?? (int) $existing['pos_x'];
            $y = $posY ?? (int) $existing['pos_y'];
            $stmt = $this->db->prepare(
                'UPDATE stickies SET content = :content, category = :cat, color = :color,
                 section = :section, pos_x = :x, pos_y = :y, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND user_id = :uid',
            );
            $stmt->execute([
                'content' => $content,
                'cat' => $category,
                'color' => $color,
                'section' => $section,
                'x' => $x,
                'y' => $y,
                'id' => $noteId,
                'uid' => $userId,
            ]);

            return $this->publicNote($this->findRaw($userId, $noteId)
                ?? throw new RuntimeException('Sticky missing after save'));
        }

        $x = $posX ?? 0;
        $y = $posY ?? $this->nextRowY($userId, $section);
        $stmt = $this->db->prepare(
            'INSERT INTO stickies (user_id, content, category, section, color, pos_x, pos_y)
             VALUES (:uid, :content, :cat, :section, :color, :x, :y)',
        );
        $stmt->execute([
            'uid' => $userId,
            'content' => $content,
            'cat' => $category,
            'section' => $section,
            'color' => $color,
            'x' => $x,
            'y' => $y,
        ]);
        $id = (int) $this->db->lastInsertId();

        return $this->publicNote($this->findRaw($userId, $id)
            ?? throw new RuntimeException('Sticky missing after create'));
    }

    /** @return array<string, mixed> */
    public function moveNote(int $userId, int $noteId, int $posX, int $posY, ?string $section = null): array
    {
        $existing = $this->findRaw($userId, $noteId);
        if ($existing === null) {
            throw new RuntimeException('Sticky not found');
        }

        $section = $section !== null ? self::normalizeLabel($section, 'board') : $existing['section'];
        $stmt = $this->db->prepare(
            'UPDATE stickies SET pos_x = :x, pos_y = :y, section = :section,
             updated_at = CURRENT_TIMESTAMP WHERE id = :id AND user_id = :uid',
        );
        $stmt->execute([
            'x' => $posX,
            'y' => $posY,
            'section' => $section,
            'id' => $noteId,
            'uid' => $userId,
        ]);

        return $this->publicNote($this->findRaw($userId, $noteId)
            ?? throw new RuntimeException('Sticky missing after move'));
    }

    public function deleteNote(int $userId, int $noteId): void
    {
        $stmt = $this->db->prepare('DELETE FROM stickies WHERE id = :id AND user_id = :uid');
        $stmt->execute(['id' => $noteId, 'uid' => $userId]);
        if ($stmt->rowCount() < 1) {
            throw new RuntimeException('Sticky not found');
        }
    }

    /** @param list<array<string, mixed>> $rows */
    public static function filterBySearch(array $rows, string $search): array
    {
        $needle = mb_strtolower(trim($search));
        if ($needle === '') {
            return $rows;
        }

        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => str_contains(mb_strtolower((string) ($row['content'] ?? '')), $needle)
                || str_contains(mb_strtolower((string) ($row['category'] ?? '')), $needle)
                || str_contains(mb_strtolower((string) ($row['section'] ?? '')), $needle),
        ));
    }

    /** @param list<array<string, mixed>> $notes */
    /** @return array<string, list<array<string, mixed>>> */
    public static function groupBySection(array $notes): array
    {
        $groups = [];
        foreach ($notes as $note) {
            $section = (string) ($note['section'] ?? 'board');
            $groups[$section][] = $note;
        }
        ksort($groups);

        return $groups;
    }

    /** @return array<string, mixed>|null */
    private function findRaw(int $userId, int $noteId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM stickies WHERE id = :id AND user_id = :uid LIMIT 1');
        $stmt->execute(['id' => $noteId, 'uid' => $userId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    private function nextRowY(int $userId, string $section): int
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(MAX(pos_y), -1) + 1 AS next_y FROM stickies WHERE user_id = :uid AND section = :section',
        );
        $stmt->execute(['uid' => $userId, 'section' => $section]);
        $row = $stmt->fetch();

        return (int) ($row['next_y'] ?? 0);
    }

    /** @param array<string, mixed> $row */
    /** @return array<string, mixed> */
    private function publicNote(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'content' => (string) $row['content'],
            'category' => (string) $row['category'],
            'section' => (string) $row['section'],
            'color' => (string) $row['color'],
            'pos_x' => (int) $row['pos_x'],
            'pos_y' => (int) $row['pos_y'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    private static function normalizeLabel(string $value, string $fallback): string
    {
        $trimmed = mb_strtolower(trim($value));
        if ($trimmed === '') {
            return $fallback;
        }

        return mb_substr($trimmed, 0, 80);
    }
}
