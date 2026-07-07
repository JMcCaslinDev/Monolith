-- Blog package — posts, SEO, views, and analytics

CREATE TABLE IF NOT EXISTS blog_posts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    slug VARCHAR(120) NOT NULL,
    title VARCHAR(200) NOT NULL,
    excerpt VARCHAR(500) NULL,
    content MEDIUMTEXT NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'draft',
    tags JSON NULL,
    meta_title VARCHAR(200) NULL,
    meta_description VARCHAR(320) NULL,
    og_image_url VARCHAR(500) NULL,
    views INT UNSIGNED NOT NULL DEFAULT 0,
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY blog_posts_slug_unique (slug),
    KEY blog_posts_status_published_idx (status, published_at),
    KEY blog_posts_user_created_idx (user_id, created_at),
    CONSTRAINT blog_posts_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_daily_views (
    post_id BIGINT UNSIGNED NOT NULL,
    view_date DATE NOT NULL,
    views INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (post_id, view_date),
    KEY blog_daily_views_date_idx (view_date),
    CONSTRAINT blog_daily_views_post_fk FOREIGN KEY (post_id) REFERENCES blog_posts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (name, description, category) VALUES
    ('projects.blog.view', 'See Blog on dashboard', 'projects'),
    ('projects.blog.open', 'Open Blog project', 'projects'),
    ('blog.posts.view', 'View blog drafts and published posts', 'blog'),
    ('blog.posts.manage', 'Create, edit, publish, and delete blog posts', 'blog'),
    ('blog.analytics.view', 'View blog post analytics', 'blog')
ON DUPLICATE KEY UPDATE description = VALUES(description), category = VALUES(category);

INSERT IGNORE INTO role_permission (role_id, permission_id)
SELECT r.id, p.id FROM roles r
JOIN permissions p ON p.name LIKE 'blog.%' OR p.name IN ('projects.blog.view', 'projects.blog.open')
WHERE r.name IN ('owner', 'admin');

INSERT IGNORE INTO role_permission (role_id, permission_id)
SELECT r.id, p.id FROM roles r
JOIN permissions p ON p.name = 'projects.blog.view'
WHERE r.name = 'viewer';
