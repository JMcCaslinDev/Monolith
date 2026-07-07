<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PermissionService;
use Tests\Support\TestCase;

final class PermissionServiceTest extends TestCase
{
    private PermissionService $perms;

    protected function setUp(): void
    {
        parent::setUp();
        $this->perms = new PermissionService($this->db);
    }

    public function test_owner_has_all_seeded_permissions(): void
    {
        $names = $this->perms->allForUser(1);
        $this->assertContains('pages.dashboard.view', $names);
        $this->assertContains('admin.permissions.manage', $names);
    }

    public function test_member_lacks_admin_permissions(): void
    {
        $memberId = $this->insertMember();
        $this->assertFalse($this->perms->can($memberId, 'admin.events.view'));
        $this->assertTrue($this->perms->can($memberId, 'tools.json-converter.use'));
    }

    public function test_direct_grant_overrides_missing_role_permission(): void
    {
        $memberId = $this->insertMember();
        $this->assertFalse($this->perms->can($memberId, 'admin.events.view'));
        $this->perms->grantUser($memberId, 'admin.events.view');
        $this->assertTrue($this->perms->can($memberId, 'admin.events.view'));
    }

    public function test_revoke_grant_removes_access(): void
    {
        $memberId = $this->insertMember();
        $this->perms->grantUser($memberId, 'admin.events.view');
        $this->perms->revokeUserGrant($memberId, 'admin.events.view');
        $this->assertFalse($this->perms->can($memberId, 'admin.events.view'));
    }

    public function test_expired_grant_does_not_apply(): void
    {
        $memberId = $this->insertMember();
        $permId = $this->db->query("SELECT id FROM permissions WHERE name = 'admin.events.view'")->fetchColumn();
        $this->db->prepare(
            'INSERT INTO grants (user_id, permission_id, expires_at) VALUES (?, ?, datetime("now", "-1 hour"))'
        )->execute([$memberId, $permId]);
        $this->assertFalse($this->perms->can($memberId, 'admin.events.view'));
    }

    public function test_set_role_replaces_previous_role(): void
    {
        $memberId = $this->insertMember();
        $this->perms->setRole($memberId, 'viewer');
        $this->assertSame('viewer', $this->perms->userRoleName($memberId));
        $this->assertFalse($this->perms->can($memberId, 'tools.json-converter.use'));
    }

    public function test_owner_role_cannot_be_stripped_via_set_role_permission(): void
    {
        $this->perms->setRolePermission('owner', 'pages.dashboard.view', false);
        $this->assertTrue($this->perms->roleHasPermission('owner', 'pages.dashboard.view'));
    }

    public function test_roles_ordered_by_level(): void
    {
        $names = array_column($this->perms->allRoles(), 'name');
        $this->assertSame(['owner', 'admin', 'member', 'viewer'], $names);
    }

    public function test_user_permission_breakdown_shows_sources(): void
    {
        $memberId = $this->insertMember();
        $this->perms->grantUser($memberId, 'admin.events.view');
        $breakdown = $this->perms->userPermissionBreakdown($memberId);
        $byName = array_column($breakdown, null, 'name');
        $this->assertContains('role', $byName['pages.dashboard.view']['sources']);
        $this->assertContains('grant', $byName['admin.events.view']['sources']);
    }

    public function test_unknown_role_and_permission_rejected(): void
    {
        $this->assertFalse($this->perms->isKnownRole('superuser'));
        $this->assertFalse($this->perms->isKnownPermission('fake.permission'));
        $this->assertTrue($this->perms->isKnownRole('owner'));
    }
}
