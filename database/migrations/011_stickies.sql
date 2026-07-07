-- Stickies quick-notes package

CREATE TABLE IF NOT EXISTS stickies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    content TEXT NOT NULL,
    category VARCHAR(80) NOT NULL DEFAULT 'general',
    section VARCHAR(80) NOT NULL DEFAULT 'board',
    color VARCHAR(24) NOT NULL DEFAULT 'yellow',
    pos_x INT NOT NULL DEFAULT 0,
    pos_y INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY stickies_user_idx (user_id),
    KEY stickies_user_category_idx (user_id, category),
    KEY stickies_user_section_idx (user_id, section),
    CONSTRAINT stickies_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (name, description, category) VALUES
    ('projects.stickies.view', 'See Stickies on dashboard', 'projects'),
    ('projects.stickies.open', 'Open Stickies project', 'projects'),
    ('stickies.manage', 'Create, edit, move, and delete own stickies', 'stickies')
ON DUPLICATE KEY UPDATE description = VALUES(description), category = VALUES(category);

INSERT IGNORE INTO role_permission (role_id, permission_id)
SELECT r.id, p.id FROM roles r
JOIN permissions p ON p.name LIKE 'stickies.%' OR p.name IN ('projects.stickies.view', 'projects.stickies.open')
WHERE r.name IN ('owner', 'admin', 'member');

INSERT IGNORE INTO role_permission (role_id, permission_id)
SELECT r.id, p.id FROM roles r
JOIN permissions p ON p.name = 'projects.stickies.view'
WHERE r.name = 'viewer';
