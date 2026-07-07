-- Budget & asset tracker package

CREATE TABLE IF NOT EXISTS budget_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    mode VARCHAR(16) NOT NULL DEFAULT 'solo',
    onboarding_complete TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY budget_profiles_user_unique (user_id),
    CONSTRAINT budget_profiles_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS budget_people (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    profile_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY budget_people_profile_idx (profile_id),
    CONSTRAINT budget_people_profile_fk FOREIGN KEY (profile_id) REFERENCES budget_profiles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS budget_income_sources (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    person_id BIGINT UNSIGNED NOT NULL,
    label VARCHAR(120) NOT NULL,
    amount_cents BIGINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY budget_income_person_idx (person_id),
    CONSTRAINT budget_income_person_fk FOREIGN KEY (person_id) REFERENCES budget_people (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS budget_expense_sources (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    person_id BIGINT UNSIGNED NOT NULL,
    category VARCHAR(80) NOT NULL,
    label VARCHAR(120) NULL,
    amount_cents BIGINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY budget_expense_person_idx (person_id),
    CONSTRAINT budget_expense_person_fk FOREIGN KEY (person_id) REFERENCES budget_people (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS budget_accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    profile_id BIGINT UNSIGNED NOT NULL,
    person_id BIGINT UNSIGNED NULL,
    kind VARCHAR(24) NOT NULL,
    name VARCHAR(120) NOT NULL,
    balance_cents BIGINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY budget_accounts_profile_idx (profile_id),
    KEY budget_accounts_person_idx (person_id),
    CONSTRAINT budget_accounts_profile_fk FOREIGN KEY (profile_id) REFERENCES budget_profiles (id) ON DELETE CASCADE,
    CONSTRAINT budget_accounts_person_fk FOREIGN KEY (person_id) REFERENCES budget_people (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (name, description, category) VALUES
    ('projects.budget-tracker.view', 'See Budget Tracker on dashboard', 'projects'),
    ('projects.budget-tracker.open', 'Open Budget Tracker project', 'projects'),
    ('budget-tracker.manage', 'Manage own budget, income, expenses, and assets', 'budget-tracker'),
    ('budget-tracker.purchase.use', 'Use purchase affordability calculator', 'budget-tracker')
ON DUPLICATE KEY UPDATE description = VALUES(description), category = VALUES(category);

INSERT IGNORE INTO role_permission (role_id, permission_id)
SELECT r.id, p.id FROM roles r
JOIN permissions p ON p.name LIKE 'budget-tracker.%' OR p.name IN ('projects.budget-tracker.view', 'projects.budget-tracker.open')
WHERE r.name IN ('owner', 'admin', 'member');

INSERT IGNORE INTO role_permission (role_id, permission_id)
SELECT r.id, p.id FROM roles r
JOIN permissions p ON p.name = 'projects.budget-tracker.view'
WHERE r.name = 'viewer';
