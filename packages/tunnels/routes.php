<?php

declare(strict_types=1);

require_once __DIR__ . '/src/TunnelService.php';

$jsonResponse = function (array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_THROW_ON_ERROR);
    exit;
};

$hubJsonBody = function (): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : [];
};

$publicTunnelRow = function (array $tunnel): array {
    return [
        'id' => (int) $tunnel['id'],
        'slug' => $tunnel['slug'],
        'label' => $tunnel['label'],
        'local_port' => (int) $tunnel['local_port'],
        'status' => $tunnel['status'],
        'expires_at' => $tunnel['expires_at'],
        'created_at' => $tunnel['created_at'],
        'stopped_at' => $tunnel['stopped_at'] ?? null,
        'public_url' => tunnel_hub_url() . '/t/' . $tunnel['slug'],
    ];
};

return [
    'GET /projects/tunnels' => fn () => dispatch(function (): void {
        $user = auth()->currentUser();
        $uid = (int) $user['id'];
        events()->record('project.opened', $uid, 'project', 'tunnels', ['project' => 'tunnels']);

        package_view('tunnels', 'app', [
            'title' => 'Tunnels',
            'fullWidth' => true,
            'hubUrl' => tunnel_hub_url(),
            'appUrl' => config('app')['url'],
            'downloadCommand' => tunnel_client_download_command(),
            'scriptUrl' => tunnel_client_script_url(),
        ]);
    }, ['auth', 'perm:projects.tunnels.open']),

    'GET /projects/tunnels/api/state' => fn () => dispatch(function () use ($jsonResponse, $publicTunnelRow): void {
        $user = auth()->currentUser();
        $uid = (int) $user['id'];
        $perms = permissions()->allForUser($uid);
        $tunnelId = (int) ($_GET['tunnel_id'] ?? 0);
        $sinceId = (int) ($_GET['since_id'] ?? 0);

        $tunnels = tunnels()->listForUser($uid);
        $rows = array_map($publicTunnelRow, $tunnels);

        $requests = [];
        $selected = null;
        if ($tunnelId > 0) {
            $selected = tunnels()->findForUser($uid, $tunnelId);
            if ($selected !== null) {
                $requests = tunnels()->requestsForTunnel($tunnelId, $sinceId);
            }
        }

        $selectedOut = null;
        if ($selected !== null) {
            $selectedOut = $publicTunnelRow($selected);
            $selectedOut['client_command'] = tunnel_client_command(
                $selected['token'],
                (int) $selected['local_port']
            );
        }

        $jsonResponse([
            'tunnels' => $rows,
            'selected' => $selectedOut,
            'requests' => $requests,
            'canCreate' => in_array('tunnels.create', $perms, true),
            'canManage' => in_array('tunnels.manage', $perms, true),
            'download_command' => tunnel_client_download_command(),
            'script_url' => tunnel_client_script_url(),
            'hub_url' => tunnel_hub_url(),
        ]);
    }, ['auth', 'perm:projects.tunnels.open'], recordPageView: false),

    'POST /projects/tunnels/create' => fn () => dispatch(function () use ($jsonResponse, $publicTunnelRow): void {
        verify_csrf();
        $user = auth()->currentUser();
        $uid = (int) $user['id'];
        $label = trim((string) ($_POST['label'] ?? ''));
        $port = (int) ($_POST['local_port'] ?? 8000);
        $ttl = (int) ($_POST['ttl_minutes'] ?? 480);

        try {
            $tunnel = tunnels()->create($uid, $label, $port, $ttl);
        } catch (Throwable $e) {
            $jsonResponse(['error' => $e->getMessage()], 400);
        }

        events()->record('tunnels.tunnel.created', $uid, 'tunnel', (string) $tunnel['id'], [
            'slug' => $tunnel['slug'],
            'local_port' => (int) $tunnel['local_port'],
        ]);

        $row = $publicTunnelRow($tunnel);
        $row['token'] = $tunnel['token'];
        $row['client_command'] = tunnel_client_command($tunnel['token'], (int) $tunnel['local_port']);
        $jsonResponse(['tunnel' => $row]);
    }, ['auth', 'perm:tunnels.create'], recordPageView: false),

    'POST /projects/tunnels/stop' => fn () => dispatch(function () use ($jsonResponse): void {
        verify_csrf();
        $user = auth()->currentUser();
        $uid = (int) $user['id'];
        $tunnelId = (int) ($_POST['tunnel_id'] ?? 0);
        if ($tunnelId < 1) {
            $jsonResponse(['error' => 'Invalid tunnel'], 400);
        }

        $tunnel = tunnels()->findForUser($uid, $tunnelId);
        if ($tunnel === null) {
            $jsonResponse(['error' => 'Tunnel not found'], 404);
        }

        if (!tunnels()->stop($uid, $tunnelId)) {
            $jsonResponse(['error' => 'Tunnel already stopped'], 400);
        }

        events()->record('tunnels.tunnel.stopped', $uid, 'tunnel', (string) $tunnelId, [
            'slug' => $tunnel['slug'],
        ]);
        $jsonResponse(['ok' => true]);
    }, ['auth', 'perm:tunnels.manage'], recordPageView: false),

    'POST /tunnel-hub/lookup-token' => fn () => dispatch(function () use ($jsonResponse, $hubJsonBody): void {
        verify_tunnel_hub_secret();
        $body = $hubJsonBody();
        $token = trim((string) ($body['token'] ?? ''));
        if ($token === '') {
            $jsonResponse(['error' => 'Missing token'], 400);
        }
        $tunnel = tunnels()->lookupByToken($token);
        if ($tunnel === null || !tunnels()->isUsable($tunnel)) {
            $jsonResponse(['error' => 'Invalid token'], 404);
        }
        $jsonResponse([
            'id' => (int) $tunnel['id'],
            'slug' => $tunnel['slug'],
            'local_port' => (int) $tunnel['local_port'],
            'status' => $tunnel['status'],
        ]);
    }, recordPageView: false),

    'POST /tunnel-hub/lookup-slug' => fn () => dispatch(function () use ($jsonResponse, $hubJsonBody): void {
        verify_tunnel_hub_secret();
        $body = $hubJsonBody();
        $slug = trim((string) ($body['slug'] ?? ''));
        if ($slug === '' || !preg_match('/^[a-z0-9]{8,16}$/', $slug)) {
            $jsonResponse(['error' => 'Invalid slug'], 400);
        }
        $tunnel = tunnels()->lookupBySlug($slug);
        if ($tunnel === null || !tunnels()->isUsable($tunnel)) {
            $jsonResponse(['error' => 'Tunnel not found'], 404);
        }
        $jsonResponse([
            'id' => (int) $tunnel['id'],
            'slug' => $tunnel['slug'],
            'local_port' => (int) $tunnel['local_port'],
            'status' => $tunnel['status'],
        ]);
    }, recordPageView: false),

    'POST /tunnel-hub/connected' => fn () => dispatch(function () use ($jsonResponse, $hubJsonBody): void {
        verify_tunnel_hub_secret();
        $body = $hubJsonBody();
        $tunnelId = (int) ($body['tunnel_id'] ?? 0);
        if ($tunnelId < 1) {
            $jsonResponse(['error' => 'Invalid tunnel'], 400);
        }
        tunnels()->markActive($tunnelId);
        $slug = trim((string) ($body['slug'] ?? ''));
        events()->record('tunnels.tunnel.connected', null, 'tunnel', (string) $tunnelId, [
            'slug' => $slug,
        ]);
        $jsonResponse(['ok' => true]);
    }, recordPageView: false),

    'POST /tunnel-hub/disconnected' => fn () => dispatch(function () use ($jsonResponse, $hubJsonBody): void {
        verify_tunnel_hub_secret();
        $body = $hubJsonBody();
        $tunnelId = (int) ($body['tunnel_id'] ?? 0);
        if ($tunnelId > 0) {
            tunnels()->setStatus($tunnelId, 'pending');
        }
        $jsonResponse(['ok' => true]);
    }, recordPageView: false),

    'POST /tunnel-hub/log-request' => fn () => dispatch(function () use ($jsonResponse, $hubJsonBody): void {
        verify_tunnel_hub_secret();
        $body = $hubJsonBody();
        $tunnelId = (int) ($body['tunnel_id'] ?? 0);
        if ($tunnelId < 1) {
            $jsonResponse(['error' => 'Invalid tunnel'], 400);
        }

        $reqHeaders = is_array($body['request_headers'] ?? null) ? $body['request_headers'] : [];
        $resHeaders = is_array($body['response_headers'] ?? null) ? $body['response_headers'] : null;
        $reqHeaderStrings = [];
        foreach ($reqHeaders as $k => $v) {
            $reqHeaderStrings[(string) $k] = is_array($v) ? implode(', ', $v) : (string) $v;
        }
        $resHeaderStrings = null;
        if ($resHeaders !== null) {
            $resHeaderStrings = [];
            foreach ($resHeaders as $k => $v) {
                $resHeaderStrings[(string) $k] = is_array($v) ? implode(', ', $v) : (string) $v;
            }
        }

        $id = tunnels()->logRequest(
            $tunnelId,
            (string) ($body['request_method'] ?? 'GET'),
            (string) ($body['request_path'] ?? '/'),
            (string) ($body['query_string'] ?? ''),
            $reqHeaderStrings,
            (string) ($body['request_body'] ?? ''),
            isset($body['response_status']) ? (int) $body['response_status'] : null,
            $resHeaderStrings,
            (string) ($body['response_body'] ?? ''),
            isset($body['duration_ms']) ? (int) $body['duration_ms'] : null,
            isset($body['client_ip']) ? (string) $body['client_ip'] : null,
            (bool) ($body['forwarded'] ?? false),
            isset($body['error_message']) ? (string) $body['error_message'] : null,
        );
        $jsonResponse(['id' => $id]);
    }, recordPageView: false),
];
