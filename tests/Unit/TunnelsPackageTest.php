<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Projects\Registry;
use Tunnels\TunnelService;
use PHPUnit\Framework\TestCase;

/** Tunnels package wiring: manifest, registry merge, and HTTP route table. */
final class TunnelsPackageTest extends TestCase
{
    /** Main tunnels UI and API routes are registered. */
    public function test_web_routes_include_tunnels_endpoints(): void
    {
        /** @var array<string, callable> $routes */
        $routes = require dirname(__DIR__, 2) . '/routes/web.php';
        $this->assertArrayHasKey('GET /projects/tunnels', $routes);
        $this->assertArrayHasKey('POST /projects/tunnels/create', $routes);
        $this->assertArrayHasKey('POST /tunnel-hub/lookup-token', $routes);
    }

    /** Tunnels audit event types are documented in the merged registry. */
    public function test_registry_includes_tunnels_events(): void
    {
        $types = array_column(Registry::packageEvents(), 'type');
        $this->assertContains('tunnels.tunnel.created', $types);
        $this->assertContains('tunnels.tunnel.stopped', $types);
        $this->assertContains('tunnels.tunnel.connected', $types);
    }

    /** Tunnels project appears in package registry with expected permissions. */
    public function test_manifest_declares_project_and_permissions(): void
    {
        $manifest = Registry::packages()['tunnels'];
        $this->assertSame('Tunnels', $manifest['project']['name']);
        $names = array_column($manifest['permissions'] ?? [], 'name');
        $this->assertContains('projects.tunnels.open', $names);
        $this->assertContains('tunnels.create', $names);
    }

    /** Sidebar template exposes client download before a tunnel is selected. */
    public function test_sidebar_shows_client_download(): void
    {
        $html = file_get_contents(dirname(__DIR__, 2) . '/packages/tunnels/views/app.php');
        $this->assertIsString($html);
        $this->assertStringContainsString('Get the client', $html);
        $this->assertStringContainsString('download="tunnel-client.mjs"', $html);
        $this->assertStringContainsString('downloadCommand', $html);
    }

    /** Standalone tunnel client is served from public/ with no npm dependencies. */
    public function test_public_tunnel_client_is_standalone(): void
    {
        $path = dirname(__DIR__, 2) . '/public/tunnel-client.mjs';
        $this->assertFileExists($path);
        $src = file_get_contents($path);
        $this->assertIsString($src);
        $this->assertStringNotContainsString("from 'ws'", $src);
        $this->assertStringContainsString('WebSocket', $src);
    }
}
