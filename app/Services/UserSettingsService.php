<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class UserSettingsService
{
    public function __construct(private PDO $db)
    {
    }

    /** @return mixed */
    public function get(int $userId, string $key, mixed $default = null): mixed
    {
        $stmt = $this->db->prepare(
            'SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = ?'
        );
        $stmt->execute([$userId, $key]);
        $raw = $stmt->fetchColumn();
        if ($raw === false) {
            return $default;
        }
        return json_decode((string) $raw, true);
    }

    public function set(int $userId, string $key, mixed $value): void
    {
        $json = json_encode($value, JSON_THROW_ON_ERROR);
        $this->db->prepare('DELETE FROM user_settings WHERE user_id = ? AND setting_key = ?')
            ->execute([$userId, $key]);
        $this->db->prepare(
            'INSERT INTO user_settings (user_id, setting_key, setting_value) VALUES (?, ?, ?)'
        )->execute([$userId, $key, $json]);
    }

    /** @param list<string> $openableProjectIds */
    /** @return list<string> */
    public function navbarProjectIds(int $userId, array $openableProjectIds): array
    {
        $saved = $this->get($userId, 'navbar_projects');
        if (!is_array($saved)) {
            return $openableProjectIds;
        }
        return array_values(array_intersect($openableProjectIds, $saved));
    }
}
