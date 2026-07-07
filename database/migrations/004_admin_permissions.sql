-- admin.permissions.manage (owner gets all via role seed; not admin category)

INSERT INTO permissions (name, description, category) VALUES
    ('admin.permissions.manage', 'Configure roles, permissions, and grants', 'permissions')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT IGNORE INTO role_permission (role_id, permission_id)
SELECT r.id, p.id FROM roles r
JOIN permissions p ON p.name = 'admin.permissions.manage'
WHERE r.name = 'owner';
