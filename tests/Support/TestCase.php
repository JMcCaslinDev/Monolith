<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Projects\Registry;
use PDO;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected PDO $db;

    protected function setUp(): void
    {
        parent::setUp();
        Registry::resetForTests();
        $this->db = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->db->exec(file_get_contents(dirname(__DIR__, 2) . '/tests/schema.sqlite.sql'));
        $this->seedBaseData();
    }

    protected function seedBaseData(): void
    {
        foreach (['owner', 'admin', 'member', 'viewer'] as $role) {
            $this->db->prepare('INSERT INTO roles (name) VALUES (?)')->execute([$role]);
        }

        $perms = [
            ['pages.dashboard.view', 'pages'],
            ['projects.devtools.view', 'projects'],
            ['projects.devtools.open', 'projects'],
            ['devtools.formatters.use', 'devtools.formatters'],
            ['devtools.formatters.json.use', 'devtools.formatters'],
            ['admin.events.view', 'admin'],
            ['admin.users.manage', 'admin'],
            ['admin.permissions.manage', 'permissions'],
        ];
        foreach ($perms as [$name, $category]) {
            $this->db->prepare('INSERT INTO permissions (name, category) VALUES (?, ?)')
                ->execute([$name, $category]);
        }

        $this->db->exec(
            'INSERT INTO role_permission (role_id, permission_id)
             SELECT r.id, p.id FROM roles r, permissions p WHERE r.name = "owner"'
        );

        $this->db->exec(
            'INSERT INTO role_permission (role_id, permission_id)
             SELECT r.id, p.id FROM roles r
             JOIN permissions p ON p.name IN ("pages.dashboard.view", "devtools.formatters.use", "devtools.formatters.json.use", "projects.devtools.view", "projects.devtools.open")
             WHERE r.name = "member"'
        );

        $this->db->prepare('INSERT INTO users (auth0_sub, email, name) VALUES (?, ?, ?)')
            ->execute(['auth0|1', 'owner@test.com', 'Owner']);
        $this->db->prepare('INSERT INTO user_role (user_id, role_id) SELECT 1, id FROM roles WHERE name = ?')
            ->execute(['owner']);
    }

    protected function insertMember(string $email = 'member@test.com'): int
    {
        $this->db->prepare('INSERT INTO users (auth0_sub, email, name) VALUES (?, ?, ?)')
            ->execute(['auth0|' . $email, $email, 'Member']);
        $id = (int) $this->db->lastInsertId();
        $this->db->prepare('INSERT INTO user_role (user_id, role_id) SELECT ?, id FROM roles WHERE name = ?')
            ->execute([$id, 'member']);
        return $id;
    }
}
