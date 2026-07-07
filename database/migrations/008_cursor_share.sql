-- Cursor Share package — community rules, skills, commands, and hooks

CREATE TABLE IF NOT EXISTS cursor_share_posts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    category VARCHAR(16) NOT NULL,
    title VARCHAR(200) NOT NULL,
    description VARCHAR(1000) NULL,
    filename VARCHAR(255) NOT NULL,
    version VARCHAR(64) NULL,
    content MEDIUMTEXT NOT NULL,
    tags JSON NULL,
    upvotes INT UNSIGNED NOT NULL DEFAULT 0,
    downvotes INT UNSIGNED NOT NULL DEFAULT 0,
    views INT UNSIGNED NOT NULL DEFAULT 0,
    downloads INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY cursor_share_posts_category_popularity_idx (category, upvotes, downvotes, views),
    KEY cursor_share_posts_user_created_idx (user_id, created_at),
    KEY cursor_share_posts_created_idx (created_at),
    CONSTRAINT cursor_share_posts_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cursor_share_votes (
    post_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    vote TINYINT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (post_id, user_id),
    CONSTRAINT cursor_share_votes_post_fk FOREIGN KEY (post_id) REFERENCES cursor_share_posts (id) ON DELETE CASCADE,
    CONSTRAINT cursor_share_votes_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (name, description, category) VALUES
    ('projects.cursor-share.view', 'See Cursor Share on dashboard', 'projects'),
    ('projects.cursor-share.open', 'Open Cursor Share project', 'projects'),
    ('cursor-share.browse', 'Browse community Cursor assets', 'cursor-share'),
    ('cursor-share.post', 'Create and edit own Cursor asset posts', 'cursor-share'),
    ('cursor-share.vote', 'Upvote and downvote community posts', 'cursor-share'),
    ('cursor-share.download', 'Download community Cursor assets', 'cursor-share')
ON DUPLICATE KEY UPDATE description = VALUES(description), category = VALUES(category);

INSERT IGNORE INTO role_permission (role_id, permission_id)
SELECT r.id, p.id FROM roles r
JOIN permissions p ON p.name LIKE 'cursor-share.%' OR p.name IN ('projects.cursor-share.view', 'projects.cursor-share.open')
WHERE r.name IN ('owner', 'member');

INSERT IGNORE INTO role_permission (role_id, permission_id)
SELECT r.id, p.id FROM roles r
JOIN permissions p ON p.name IN ('projects.cursor-share.view', 'cursor-share.browse', 'cursor-share.download')
WHERE r.name = 'viewer';
