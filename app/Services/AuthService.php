<?php

declare(strict_types=1);

namespace App\Services;

use Auth0\SDK\Auth0;
use Auth0\SDK\Configuration\SdkConfiguration;
use PDO;

final class AuthService
{
    private ?Auth0 $auth0 = null;

    public function __construct(
        private PDO $db,
        private PermissionService $permissions,
        private EventRecorder $events,
    ) {}

    private function auth0(): Auth0
    {
        if ($this->auth0) {
            return $this->auth0;
        }
        $cfg = config('app')['auth0'];
        return $this->auth0 = new Auth0(new SdkConfiguration([
            'domain' => $cfg['domain'],
            'clientId' => $cfg['client_id'],
            'clientSecret' => $cfg['client_secret'],
            'cookieSecret' => $cfg['cookie_secret'],
            'redirectUri' => config('app')['url'] . '/auth/callback',
        ]));
    }

    public function login(): void
    {
        $cfg = config('app')['auth0'];
        if ($cfg['domain'] === '' || $cfg['client_id'] === '' || $cfg['cookie_secret'] === '') {
            http_response_code(503);
            echo 'Auth0 not configured. See docs/ACCOUNT_SETUP.md';
            exit;
        }
        $this->events->record('auth.login.started', null, 'auth', null);
        header('Location: ' . $this->auth0()->login());
        exit;
    }

    public function handleCallback(): void
    {
        if (!isset($_GET['code'])) {
            $this->events->record('auth.failed', null, 'auth', null, ['reason' => 'missing_code']);
            http_response_code(400);
            echo 'Auth callback missing code.';
            exit;
        }

        try {
            $this->auth0()->exchange();
        } catch (\Throwable $e) {
            $this->events->record('auth.failed', null, 'auth', null, ['reason' => 'exchange_failed']);
            http_response_code(401);
            echo 'Authentication failed.';
            exit;
        }

        $creds = $this->auth0()->getCredentials();
        $profile = $creds?->user ?? null;
        if (!$profile || empty($profile['sub'])) {
            $this->events->record('auth.failed', null, 'auth', null, ['reason' => 'no_profile']);
            http_response_code(401);
            echo 'No user profile.';
            exit;
        }

        $user = $this->upsertUser($profile);
        $_SESSION['user_id'] = $user['id'];
        $this->events->record('auth.login', $user['id'], 'user', (string) $user['id'], [
            'email' => $user['email'] ?? '',
        ]);

        header('Location: /');
        exit;
    }

    public function logout(): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        if ($userId) {
            $this->events->record('auth.logout', (int) $userId, 'user', (string) $userId);
        }
        unset($_SESSION['user_id']);
        header('Location: ' . $this->auth0()->logout(config('app')['url']));
        exit;
    }

    /** @return array<string, mixed>|null */
    public function currentUser(): ?array
    {
        $id = $_SESSION['user_id'] ?? null;
        if (!$id) {
            return null;
        }
        static $cache = [];
        if (isset($cache[$id])) {
            return $cache[$id];
        }
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        $cache[$id] = $user ?: null;
        return $cache[$id];
    }

    /** @param array<string, mixed> $profile */
    private function upsertUser(array $profile): array
    {
        $sub = (string) $profile['sub'];
        $email = (string) ($profile['email'] ?? '');
        $name = $profile['name'] ?? $profile['nickname'] ?? null;

        $stmt = $this->db->prepare('SELECT * FROM users WHERE auth0_sub = ?');
        $stmt->execute([$sub]);
        $existing = $stmt->fetch();
        if ($existing) {
            $this->db->prepare('UPDATE users SET email = ?, name = ? WHERE id = ?')
                ->execute([$email, $name, $existing['id']]);
            return $existing;
        }

        $this->db->prepare('INSERT INTO users (auth0_sub, email, name) VALUES (?, ?, ?)')
            ->execute([$sub, $email, $name]);
        $id = (int) $this->db->lastInsertId();
        $user = ['id' => $id, 'auth0_sub' => $sub, 'email' => $email, 'name' => $name];

        $this->bootstrapRole($user);
        return $user;
    }

    /** @param array<string, mixed> $user */
    private function bootstrapRole(array $user): void
    {
        $count = (int) $this->db->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $bootstrapEmail = $_ENV['BOOTSTRAP_ADMIN_EMAIL'] ?? '';
        $isBootstrap = $bootstrapEmail !== '' && strcasecmp($user['email'], $bootstrapEmail) === 0;

        if ($count === 1 || $isBootstrap) {
            $this->permissions->assignRole($user['id'], 'owner');
            $this->events->record('admin.role.changed', $user['id'], 'user', (string) $user['id'], [
                'role' => 'owner',
                'reason' => $count === 1 ? 'first_user' : 'bootstrap_email',
            ]);
        } else {
            $this->permissions->assignRole($user['id'], 'member');
            $this->events->record('admin.role.changed', $user['id'], 'user', (string) $user['id'], [
                'role' => 'member',
                'reason' => 'new_user_default',
            ]);
        }
    }
}
