<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class EventRecorder
{
    private static ?string $correlationId = null;

    public function __construct(private PDO $db) {}

    public static function correlationId(): string
    {
        return self::$correlationId ??= bin2hex(random_bytes(8));
    }

    public function record(
        string $type,
        ?int $actorId = null,
        ?string $subjectType = null,
        ?string $subjectId = null,
        array $payload = [],
    ): void {
        $payload = array_merge($this->requestContext(), $payload);

        $stmt = $this->db->prepare(
            'INSERT INTO events (correlation_id, type, actor_id, subject_type, subject_id, payload, ip, user_agent)
             VALUES (:correlation_id, :type, :actor_id, :subject_type, :subject_id, :payload, :ip, :user_agent)'
        );
        $stmt->execute([
            'correlation_id' => self::correlationId(),
            'type' => $type,
            'actor_id' => $actorId,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT'])
                ? substr($_SERVER['HTTP_USER_AGENT'], 0, 512)
                : null,
        ]);
    }

    /** @return array<string, string> */
    private function requestContext(): array
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $from = isset($_SERVER['HTTP_REFERER'])
            ? (parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) ?: null)
            : null;

        $ctx = [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'path' => $path,
        ];
        if ($from && $from !== $path) {
            $ctx['from'] = $from;
        }
        return $ctx;
    }

    /** @return list<array<string, mixed>> */
    public function recent(int $limit = 100): array
    {
        $stmt = $this->db->prepare(
            'SELECT e.*, u.email AS actor_email
             FROM events e
             LEFT JOIN users u ON u.id = e.actor_id
             ORDER BY e.created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
