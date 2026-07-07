-- Tunnels package (ngrok-style HTTP tunneling)

CREATE TABLE IF NOT EXISTS tunnels (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    slug VARCHAR(16) NOT NULL,
    token CHAR(64) NOT NULL,
    label VARCHAR(128) NULL,
    local_port SMALLINT UNSIGNED NOT NULL DEFAULT 8000,
    status VARCHAR(16) NOT NULL DEFAULT 'pending',
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    stopped_at TIMESTAMP NULL,
    UNIQUE KEY tunnels_slug_unique (slug),
    UNIQUE KEY tunnels_token_unique (token),
    KEY tunnels_user_created_idx (user_id, created_at),
    CONSTRAINT tunnels_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tunnel_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tunnel_id BIGINT UNSIGNED NOT NULL,
    request_method VARCHAR(16) NOT NULL,
    request_path VARCHAR(2048) NOT NULL,
    query_string VARCHAR(2048) NULL,
    request_headers JSON NULL,
    request_body MEDIUMTEXT NULL,
    request_body_bytes INT UNSIGNED NOT NULL DEFAULT 0,
    response_status SMALLINT UNSIGNED NULL,
    response_headers JSON NULL,
    response_body MEDIUMTEXT NULL,
    response_body_bytes INT UNSIGNED NOT NULL DEFAULT 0,
    duration_ms INT UNSIGNED NULL,
    client_ip VARCHAR(45) NULL,
    forwarded TINYINT(1) NOT NULL DEFAULT 0,
    error_message VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY tunnel_requests_tunnel_created_idx (tunnel_id, created_at),
    CONSTRAINT tunnel_requests_tunnel_fk FOREIGN KEY (tunnel_id) REFERENCES tunnels (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (name, description, category) VALUES
    ('projects.tunnels.view', 'See Tunnels on dashboard', 'projects'),
    ('projects.tunnels.open', 'Open Tunnels project', 'projects'),
    ('tunnels.create', 'Create HTTP tunnels', 'tunnels'),
    ('tunnels.manage', 'Stop and manage own tunnels', 'tunnels')
ON DUPLICATE KEY UPDATE description = VALUES(description), category = VALUES(category);

INSERT IGNORE INTO role_permission (role_id, permission_id)
SELECT r.id, p.id FROM roles r
JOIN permissions p ON p.name LIKE 'tunnels.%' OR p.name IN ('projects.tunnels.view', 'projects.tunnels.open')
WHERE r.name IN ('owner', 'member');

INSERT IGNORE INTO role_permission (role_id, permission_id)
SELECT r.id, p.id FROM roles r
JOIN permissions p ON p.name = 'projects.tunnels.view'
WHERE r.name = 'viewer';
