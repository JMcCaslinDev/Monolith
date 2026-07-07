<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class PermissionService
{
    /** @var array<string, int> highest access first */
    private const ROLE_LEVEL = ['owner' => 0, 'admin' => 1, 'member' => 2, 'viewer' => 3];

    public function __construct(private PDO $db) {}

    public function can(int $userId, string $permission): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM (
                SELECT p.name FROM user_role ur
                JOIN role_permission rp ON rp.role_id = ur.role_id
                JOIN permissions p ON p.id = rp.permission_id
                WHERE ur.user_id = :uid
                UNION
                SELECT p.name FROM grants g
                JOIN permissions p ON p.id = g.permission_id
                WHERE g.user_id = :uid2
                  AND (g.expires_at IS NULL OR g.expires_at > UTC_TIMESTAMP())
            ) AS perms WHERE name = :perm LIMIT 1'
        );
        $stmt->execute(['uid' => $userId, 'uid2' => $userId, 'perm' => $permission]);
        return (bool) $stmt->fetchColumn();
    }

    /** @return list<string> */
    public function allForUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT DISTINCT name FROM (
                SELECT p.name FROM user_role ur
                JOIN role_permission rp ON rp.role_id = ur.role_id
                JOIN permissions p ON p.id = rp.permission_id
                WHERE ur.user_id = :uid
                UNION
                SELECT p.name FROM grants g
                JOIN permissions p ON p.id = g.permission_id
                WHERE g.user_id = :uid2
                  AND (g.expires_at IS NULL OR g.expires_at > UTC_TIMESTAMP())
            ) AS perms ORDER BY name'
        );
        $stmt->execute(['uid' => $userId, 'uid2' => $userId]);
        return array_column($stmt->fetchAll(), 'name');
    }

    /** @return list<array<string, mixed>> */
    public function allPermissions(): array
    {
        return $this->db->query('SELECT * FROM permissions ORDER BY category, name')->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function allRoles(): array
    {
        $roles = $this->db->query('SELECT * FROM roles')->fetchAll();
        usort($roles, fn ($a, $b) => (self::ROLE_LEVEL[$a['name']] ?? 99) <=> (self::ROLE_LEVEL[$b['name']] ?? 99));
        return $roles;
    }

    /** @param list<string> $roleNames */
    /** @return list<string> */
    public function sortRoleNames(array $roleNames): array
    {
        usort($roleNames, fn ($a, $b) => (self::ROLE_LEVEL[$a] ?? 99) <=> (self::ROLE_LEVEL[$b] ?? 99));
        return $roleNames;
    }

    public function userRoleName(int $userId): ?string
    {
        $stmt = $this->db->prepare(
            'SELECT r.name FROM user_role ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $name = $stmt->fetchColumn();
        return $name !== false ? (string) $name : null;
    }

    /** @return list<array{name: string, description: string, sources: list<string>}> */
    public function userPermissionBreakdown(int $userId): array
    {
        $map = [];

        $stmt = $this->db->prepare(
            'SELECT p.name, p.description FROM user_role ur
             JOIN role_permission rp ON rp.role_id = ur.role_id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE ur.user_id = ?'
        );
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll() as $row) {
            $map[$row['name']] ??= ['name' => $row['name'], 'description' => (string) ($row['description'] ?? ''), 'sources' => []];
            if (!in_array('role', $map[$row['name']]['sources'], true)) {
                $map[$row['name']]['sources'][] = 'role';
            }
        }

        foreach ($this->userGrants($userId) as $grant) {
            $name = $grant['name'];
            $map[$name] ??= ['name' => $name, 'description' => (string) ($grant['description'] ?? ''), 'sources' => []];
            if (!in_array('grant', $map[$name]['sources'], true)) {
                $map[$name]['sources'][] = 'grant';
            }
        }

        $out = array_values($map);
        usort($out, fn ($a, $b) => strcmp($a['name'], $b['name']));
        return $out;
    }

    /** @return array<int, list<int>> role_id => permission ids */
    public function rolePermissionMap(): array
    {
        $map = [];
        foreach ($this->db->query('SELECT role_id, permission_id FROM role_permission')->fetchAll() as $row) {
            $map[(int) $row['role_id']][] = (int) $row['permission_id'];
        }
        return $map;
    }

    public function roleHasPermission(string $roleName, string $permissionName): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM role_permission rp
             JOIN roles r ON r.id = rp.role_id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE r.name = ? AND p.name = ?'
        );
        $stmt->execute([$roleName, $permissionName]);
        return (bool) $stmt->fetchColumn();
    }

    public function setRolePermission(string $roleName, string $permissionName, bool $enabled): void
    {
        // ponytail: owner role always retains full permission set — cannot strip via UI
        if ($roleName === 'owner' && !$enabled) {
            return;
        }
        $roleId = $this->roleId($roleName);
        $permId = $this->permissionId($permissionName);
        if ($enabled) {
            $this->db->prepare('INSERT IGNORE INTO role_permission (role_id, permission_id) VALUES (?, ?)')
                ->execute([$roleId, $permId]);
        } else {
            $this->db->prepare('DELETE FROM role_permission WHERE role_id = ? AND permission_id = ?')
                ->execute([$roleId, $permId]);
        }
    }

    public function assignRole(int $userId, string $roleName): void
    {
        $roleId = $this->roleId($roleName);
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO user_role (user_id, role_id) VALUES (:uid, :rid)'
        );
        $stmt->execute(['uid' => $userId, 'rid' => $roleId]);
    }

    public function setRole(int $userId, string $roleName): void
    {
        $this->db->prepare('DELETE FROM user_role WHERE user_id = ?')->execute([$userId]);
        $this->assignRole($userId, $roleName);
    }

    /** @return list<array<string, mixed>> */
    public function userGrants(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.name, p.description, g.id FROM grants g
             JOIN permissions p ON p.id = g.permission_id
             WHERE g.user_id = ? ORDER BY p.name'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function grantUser(int $userId, string $permissionName): void
    {
        $this->db->prepare('INSERT IGNORE INTO grants (user_id, permission_id) VALUES (?, ?)')
            ->execute([$userId, $this->permissionId($permissionName)]);
    }

    public function revokeUserGrant(int $userId, string $permissionName): void
    {
        $this->db->prepare(
            'DELETE g FROM grants g
             JOIN permissions p ON p.id = g.permission_id
             WHERE g.user_id = ? AND p.name = ?'
        )->execute([$userId, $permissionName]);
    }

    public function isKnownRole(string $name): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM roles WHERE name = ?');
        $stmt->execute([$name]);
        return (bool) $stmt->fetchColumn();
    }

    public function isKnownPermission(string $name): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM permissions WHERE name = ?');
        $stmt->execute([$name]);
        return (bool) $stmt->fetchColumn();
    }

    private function roleId(string $name): int
    {
        $stmt = $this->db->prepare('SELECT id FROM roles WHERE name = ?');
        $stmt->execute([$name]);
        $id = $stmt->fetchColumn();
        if ($id === false) {
            throw new \RuntimeException("Unknown role: {$name}");
        }
        return (int) $id;
    }

    private function permissionId(string $name): int
    {
        $stmt = $this->db->prepare('SELECT id FROM permissions WHERE name = ?');
        $stmt->execute([$name]);
        $id = $stmt->fetchColumn();
        if ($id === false) {
            throw new \RuntimeException("Unknown permission: {$name}");
        }
        return (int) $id;
    }
}
