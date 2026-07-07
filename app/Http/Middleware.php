<?php

declare(strict_types=1);

namespace App\Http;

use App\Services\AuthService;
use App\Services\EventRecorder;
use App\Services\PermissionService;

final class Middleware
{
    public function __construct(
        private AuthService $auth,
        private PermissionService $permissions,
        private EventRecorder $events,
    ) {
    }

    public function auth(): void
    {
        if ($this->auth->currentUser()) {
            return;
        }
        header('Location: /login');
        exit;
    }

    public function guest(): void
    {
        if (!$this->auth->currentUser()) {
            return;
        }
        header('Location: /');
        exit;
    }

    public function permission(string $name): void
    {
        $user = $this->auth->currentUser();
        if (!$user) {
            header('Location: /login');
            exit;
        }
        if ($this->permissions->can((int) $user['id'], $name)) {
            $this->events->record('permission.granted', (int) $user['id'], 'permission', $name, [
                'permission' => $name,
            ]);
            return;
        }
        $this->events->record('permission.denied', (int) $user['id'], 'permission', $name, [
            'permission' => $name,
        ]);
        http_response_code(403);
        view('errors/403', ['title' => 'Forbidden', 'permission' => $name]);
        exit;
    }
}
