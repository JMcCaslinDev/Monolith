<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PermissionService;
use Tests\Support\TestCase;

/** Role-based and grant-based permission checks that gate every protected route. */
final class PermissionServiceTest extends TestCase
{
    private PermissionService $perms;

    protected function setUp(): void
    {
        parent::setUp();
        $this->perms = new PermissionService($this->db);
    }

    /** Owner role receives every seeded permission — platform admins must not be locked out. */
    public function test_owner_has_all_seeded_permissions(): void
    {
        $names = $this->perms->allForUser(1);
        $this->assertContains('pages.dashboard.view', $names);
        $this->assertContains('admin.permissions.manage', $names);
    }

    /** Member role cannot access admin routes but can use assigned tools. */
    public function test_member_lacks_admin_permissions(): void
    {
        $memberId = $this->insertMember();
        $this->assertFalse($this->perms->can($memberId, 'admin.events.view'));
        $this->assertTrue($this->perms->can($memberId, 'devtools.formatters.json.use'));
    }

    /** Direct grants can grant a permission not included in the user's role. */
    public function test_direct_grant_overrides_missing_role_permission(): void
    {
        $memberId = $this->insertMember();
        $this->assertFalse($this->perms->can($memberId, 'admin.events.view'));
        $this->perms->grantUser($memberId, 'admin.events.view');
        $this->assertTrue($this->perms->can($memberId, 'admin.events.view'));
    }

    /** Revoking a grant removes access immediately — temporary access must not linger. */
    public function test_revoke_grant_removes_access(): void
    {
        $memberId = $this->insertMember();
        $this->perms->grantUser($memberId, 'admin.events.view');
        $this->perms->revokeUserGrant($memberId, 'admin.events.view');
        $this->assertFalse($this->perms->can($memberId, 'admin.events.view'));
    }

    /** Expired grants are ignored so time-limited access actually expires. */
    public function test_expired_grant_does_not_apply(): void
    {
        $memberId = $this->insertMember();
        $permId = $this->db->query("SELECT id FROM permissions WHERE name = 'admin.events.view'")->fetchColumn();
        $this->db->prepare(
            'INSERT INTO grants (user_id, permission_id, expires_at) VALUES (?, ?, datetime("now", "-1 hour"))'
        )->execute([$memberId, $permId]);
        $this->assertFalse($this->perms->can($memberId, 'admin.events.view'));
    }

    /** setRole replaces the previous role instead of stacking roles. */
    public function test_set_role_replaces_previous_role(): void
    {
        $memberId = $this->insertMember();
        $this->perms->setRole($memberId, 'viewer');
        $this->assertSame('viewer', $this->perms->userRoleName($memberId));
        $this->assertFalse($this->perms->can($memberId, 'devtools.formatters.json.use'));
    }

    /** Owner permissions cannot be stripped via role matrix — prevents accidental lockout. */
    public function test_owner_role_cannot_be_stripped_via_set_role_permission(): void
    {
        $this->perms->setRolePermission('owner', 'pages.dashboard.view', false);
        $this->assertTrue($this->perms->roleHasPermission('owner', 'pages.dashboard.view'));
    }

    /** Roles list in admin UI follows hierarchy: owner > admin > member > viewer. */
    public function test_roles_ordered_by_level(): void
    {
        $names = array_column($this->perms->allRoles(), 'name');
        $this->assertSame(['owner', 'admin', 'member', 'viewer'], $names);
    }

    /** Permission breakdown shows whether access comes from role or direct grant. */
    public function test_user_permission_breakdown_shows_sources(): void
    {
        $memberId = $this->insertMember();
        $this->perms->grantUser($memberId, 'admin.events.view');
        $breakdown = $this->perms->userPermissionBreakdown($memberId);
        $byName = array_column($breakdown, null, 'name');
        $this->assertContains('role', $byName['pages.dashboard.view']['sources']);
        $this->assertContains('grant', $byName['admin.events.view']['sources']);
    }

    /** Unknown roles and permissions are rejected to prevent typos in admin UI. */
    public function test_unknown_role_and_permission_rejected(): void
    {
        $this->assertFalse($this->perms->isKnownRole('superuser'));
        $this->assertFalse($this->perms->isKnownPermission('fake.permission'));
        $this->assertTrue($this->perms->isKnownRole('owner'));
    }

    private function giveRole(int $userId, string $role): void
    {
        $this->perms->setRole($userId, $role);
    }

    /** A holder of admin.users.manage who is not an owner cannot promote anyone to owner. */
    public function test_non_owner_cannot_assign_owner_role(): void
    {
        $admin = $this->insertMember('admin@test.com');
        $this->giveRole($admin, 'admin');
        $target = $this->insertMember('target@test.com');

        $error = $this->perms->changeRole($admin, $target, 'owner');

        $this->assertNotNull($error);
        $this->assertSame(['member'], $this->roleNames($target));
    }

    /** A non-owner cannot demote an existing owner. */
    public function test_non_owner_cannot_demote_owner(): void
    {
        $admin = $this->insertMember('admin@test.com');
        $this->giveRole($admin, 'admin');

        $this->assertNotNull($this->perms->changeRole($admin, 1, 'member'));
        $this->assertSame(['owner'], $this->roleNames(1));
    }

    /** Nobody can change their own role, so the last owner cannot demote themselves. */
    public function test_cannot_change_own_role_or_remove_last_owner(): void
    {
        $this->assertNotNull($this->perms->changeRole(1, 1, 'member'));
        $this->assertSame(['owner'], $this->roleNames(1));
    }

    /** An owner can promote a member to owner and demote another owner when two exist. */
    public function test_owner_can_manage_other_owners(): void
    {
        $target = $this->insertMember('target@test.com');

        $this->assertNull($this->perms->changeRole(1, $target, 'owner'));
        $this->assertSame(['owner'], $this->roleNames($target));
        $this->assertNull($this->perms->changeRole(1, $target, 'admin'));
        $this->assertSame(['admin'], $this->roleNames($target));
    }

    /** Routine non-owner role changes keep working. */
    public function test_admin_can_move_member_to_viewer(): void
    {
        $admin = $this->insertMember('admin@test.com');
        $this->giveRole($admin, 'admin');
        $target = $this->insertMember('target@test.com');

        $this->assertNull($this->perms->changeRole($admin, $target, 'viewer'));
        $this->assertSame(['viewer'], $this->roleNames($target));
    }

    /** @return list<string> */
    private function roleNames(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT r.name FROM user_role ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ?'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
}
