<?php

declare(strict_types=1);

namespace Tunnels;

use PDO;
use RuntimeException;

/** CRUD and lookup for HTTP tunnels and their request log. */
final class TunnelService
{
    private const SLUG_CHARS = 'abcdefghijklmnopqrstuvwxyz0123456789';
    private const MAX_BODY_BYTES = 65536;
    private const DEFAULT_TTL_MINUTES = 480;

    public function __construct(private readonly PDO $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function listForUser(int $userId): array
    {
        $this->expireStale();
        $stmt = $this->db->prepare(
            'SELECT id, slug, label, local_port, status, expires_at, created_at, stopped_at
             FROM tunnels
             WHERE user_id = :uid
             ORDER BY created_at DESC
             LIMIT 50'
        );
        $stmt->execute(['uid' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed>|null */
    public function findForUser(int $userId, int $tunnelId): ?array
    {
        $this->expireStale();
        $stmt = $this->db->prepare(
            'SELECT id, user_id, slug, token, label, local_port, status, expires_at, created_at, stopped_at
             FROM tunnels
             WHERE id = :id AND user_id = :uid
             LIMIT 1'
        );
        $stmt->execute(['id' => $tunnelId, 'uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /** @return array<string, mixed> */
    public function create(int $userId, string $label, int $localPort, int $ttlMinutes = self::DEFAULT_TTL_MINUTES): array
    {
        if ($localPort < 1 || $localPort > 65535) {
            throw new RuntimeException('Invalid local port');
        }
        $ttlMinutes = max(5, min(1440, $ttlMinutes));
        $label = trim($label);
        if (strlen($label) > 128) {
            $label = substr($label, 0, 128);
        }

        $slug = $this->uniqueSlug();
        $token = bin2hex(random_bytes(32));
        $expiresAt = (new \DateTimeImmutable('+' . $ttlMinutes . ' minutes'))->format('Y-m-d H:i:s');

        $stmt = $this->db->prepare(
            'INSERT INTO tunnels (user_id, slug, token, label, local_port, status, expires_at)
             VALUES (:uid, :slug, :token, :label, :port, :status, :expires)'
        );
        $stmt->execute([
            'uid' => $userId,
            'slug' => $slug,
            'token' => $token,
            'label' => $label !== '' ? $label : null,
            'port' => $localPort,
            'status' => 'pending',
            'expires' => $expiresAt,
        ]);

        $id = (int) $this->db->lastInsertId();
        $row = $this->findForUser($userId, $id);
        if ($row === null) {
            throw new RuntimeException('Failed to create tunnel');
        }

        return $row;
    }

    public function stop(int $userId, int $tunnelId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE tunnels
             SET status = 'stopped', stopped_at = CURRENT_TIMESTAMP
             WHERE id = :id AND user_id = :uid AND status IN ('pending', 'active')"
        );
        $stmt->execute(['id' => $tunnelId, 'uid' => $userId]);

        return $stmt->rowCount() > 0;
    }

    /** @return array<string, mixed>|null */
    public function lookupByToken(string $token): ?array
    {
        $this->expireStale();
        $stmt = $this->db->prepare(
            "SELECT id, user_id, slug, token, label, local_port, status, expires_at
             FROM tunnels
             WHERE token = :token AND status IN ('pending', 'active')
             LIMIT 1"
        );
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function lookupBySlug(string $slug): ?array
    {
        $this->expireStale();
        $stmt = $this->db->prepare(
            "SELECT id, user_id, slug, token, label, local_port, status, expires_at
             FROM tunnels
             WHERE slug = :slug AND status IN ('pending', 'active')
             LIMIT 1"
        );
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function setStatus(int $tunnelId, string $status): void
    {
        if (!in_array($status, ['pending', 'active', 'stopped', 'expired'], true)) {
            return;
        }
        $stmt = $this->db->prepare('UPDATE tunnels SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $tunnelId]);
    }

    public function markActive(int $tunnelId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE tunnels SET status = 'active' WHERE id = :id AND status = 'pending'"
        );
        $stmt->execute(['id' => $tunnelId]);
    }

    /**
     * @param array<string, string> $requestHeaders
     * @param array<string, string>|null $responseHeaders
     */
    public function logRequest(
        int $tunnelId,
        string $method,
        string $path,
        string $queryString,
        array $requestHeaders,
        string $requestBody,
        ?int $responseStatus,
        ?array $responseHeaders,
        string $responseBody,
        ?int $durationMs,
        ?string $clientIp,
        bool $forwarded,
        ?string $errorMessage = null,
    ): int {
        $reqBytes = strlen($requestBody);
        $resBytes = strlen($responseBody);
        if (strlen($requestBody) > self::MAX_BODY_BYTES) {
            $requestBody = substr($requestBody, 0, self::MAX_BODY_BYTES);
        }
        if (strlen($responseBody) > self::MAX_BODY_BYTES) {
            $responseBody = substr($responseBody, 0, self::MAX_BODY_BYTES);
        }

        $stmt = $this->db->prepare(
            'INSERT INTO tunnel_requests (
                tunnel_id, request_method, request_path, query_string,
                request_headers, request_body, request_body_bytes,
                response_status, response_headers, response_body, response_body_bytes,
                duration_ms, client_ip, forwarded, error_message
             ) VALUES (
                :tid, :method, :path, :query,
                :req_headers, :req_body, :req_bytes,
                :res_status, :res_headers, :res_body, :res_bytes,
                :duration, :ip, :forwarded, :error
             )'
        );
        $stmt->execute([
            'tid' => $tunnelId,
            'method' => strtoupper($method),
            'path' => $path,
            'query' => $queryString !== '' ? $queryString : null,
            'req_headers' => json_encode($this->redactHeaders($requestHeaders), JSON_THROW_ON_ERROR),
            'req_body' => $requestBody !== '' ? $requestBody : null,
            'req_bytes' => $reqBytes,
            'res_status' => $responseStatus,
            'res_headers' => $responseHeaders !== null
                ? json_encode($this->redactHeaders($responseHeaders), JSON_THROW_ON_ERROR)
                : null,
            'res_body' => $responseBody !== '' ? $responseBody : null,
            'res_bytes' => $resBytes,
            'duration' => $durationMs,
            'ip' => $clientIp,
            'forwarded' => $forwarded ? 1 : 0,
            'error' => $errorMessage,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function requestsForTunnel(int $tunnelId, int $sinceId = 0, int $limit = 100): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, request_method, request_path, query_string,
                    request_headers, request_body, request_body_bytes,
                    response_status, response_headers, response_body, response_body_bytes,
                    duration_ms, client_ip, forwarded, error_message, created_at
             FROM tunnel_requests
             WHERE tunnel_id = :tid AND id > :since
             ORDER BY id ASC
             LIMIT :lim'
        );
        $stmt->bindValue('tid', $tunnelId, PDO::PARAM_INT);
        $stmt->bindValue('since', max(0, $sinceId), PDO::PARAM_INT);
        $stmt->bindValue('lim', max(1, min(200, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function isUsable(array $tunnel): bool
    {
        if (!in_array($tunnel['status'] ?? '', ['pending', 'active'], true)) {
            return false;
        }
        $expires = strtotime((string) ($tunnel['expires_at'] ?? ''));
        if ($expires !== false && $expires < time()) {
            return false;
        }

        return true;
    }

    private function expireStale(): void
    {
        $this->db->exec(
            "UPDATE tunnels SET status = 'expired'
             WHERE status IN ('pending', 'active') AND expires_at < CURRENT_TIMESTAMP"
        );
    }

    private function uniqueSlug(): string
    {
        for ($i = 0; $i < 20; $i++) {
            $slug = $this->randomSlug();
            $stmt = $this->db->prepare('SELECT 1 FROM tunnels WHERE slug = :slug LIMIT 1');
            $stmt->execute(['slug' => $slug]);
            if ($stmt->fetchColumn() === false) {
                return $slug;
            }
        }

        throw new RuntimeException('Could not allocate tunnel slug');
    }

    private function randomSlug(): string
    {
        $chars = self::SLUG_CHARS;
        $max = strlen($chars) - 1;
        $slug = '';
        for ($i = 0; $i < 12; $i++) {
            $slug .= $chars[random_int(0, $max)];
        }

        return $slug;
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function redactHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $name => $value) {
            $lower = strtolower($name);
            if (in_array($lower, ['authorization', 'cookie', 'set-cookie', 'x-api-key'], true)) {
                $out[$name] = '[redacted]';
            } else {
                $out[$name] = $value;
            }
        }

        return $out;
    }
}
