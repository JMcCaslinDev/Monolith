-- Tunnels package removed. Idempotent so databases that ran the old 007 migration are cleaned up.

DELETE rp FROM role_permission rp
JOIN permissions p ON p.id = rp.permission_id
WHERE p.name LIKE 'tunnels.%' OR p.name IN ('projects.tunnels.view', 'projects.tunnels.open');

DELETE g FROM grants g
JOIN permissions p ON p.id = g.permission_id
WHERE p.name LIKE 'tunnels.%' OR p.name IN ('projects.tunnels.view', 'projects.tunnels.open');

DELETE FROM permissions WHERE name LIKE 'tunnels.%' OR name IN ('projects.tunnels.view', 'projects.tunnels.open');

DROP TABLE IF EXISTS tunnel_requests;
DROP TABLE IF EXISTS tunnels;
