<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tunnels\TunnelService;
use Tests\Support\TestCase;

/** Tunnel CRUD, lookup, and request logging for the HTTP tunnel feature. */
final class TunnelServiceTest extends TestCase
{
    private TunnelService $tunnels;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tunnels = new TunnelService($this->db);
    }

    /** Users can create tunnels with unique slugs and retrieve them. */
    public function test_create_and_find_tunnel(): void
    {
        $userId = $this->insertMember();
        $row = $this->tunnels->create($userId, 'webhook test', 3000, 60);
        $this->assertMatchesRegularExpression('/^[a-z0-9]{12}$/', $row['slug']);
        $this->assertSame(3000, (int) $row['local_port']);
        $found = $this->tunnels->findForUser($userId, (int) $row['id']);
        $this->assertNotNull($found);
        $this->assertSame($row['token'], $found['token']);
    }

    /** Hub can resolve active tunnels by token and slug for forwarding. */
    public function test_lookup_by_token_and_slug(): void
    {
        $userId = $this->insertMember();
        $row = $this->tunnels->create($userId, '', 8000, 60);
        $byToken = $this->tunnels->lookupByToken($row['token']);
        $bySlug = $this->tunnels->lookupBySlug($row['slug']);
        $this->assertSame((int) $row['id'], (int) $byToken['id']);
        $this->assertSame((int) $row['id'], (int) $bySlug['id']);
    }

    /** Stopped tunnels are no longer returned by hub lookup helpers. */
    public function test_stop_prevents_lookup(): void
    {
        $userId = $this->insertMember();
        $row = $this->tunnels->create($userId, '', 8000, 60);
        $this->assertTrue($this->tunnels->stop($userId, (int) $row['id']));
        $this->assertNull($this->tunnels->lookupByToken($row['token']));
    }

    /** Request log stores metadata and redacts sensitive headers. */
    public function test_log_request_redacts_authorization(): void
    {
        $userId = $this->insertMember();
        $row = $this->tunnels->create($userId, '', 8000, 60);
        $id = $this->tunnels->logRequest(
            (int) $row['id'],
            'POST',
            '/hook',
            'x=1',
            ['Authorization' => 'Bearer secret', 'Content-Type' => 'application/json'],
            '{"ok":true}',
            200,
            ['Content-Type' => 'application/json'],
            '{"received":true}',
            12,
            '127.0.0.1',
            true,
        );
        $this->assertGreaterThan(0, $id);
        $requests = $this->tunnels->requestsForTunnel((int) $row['id']);
        $this->assertCount(1, $requests);
        $headers = json_decode((string) $requests[0]['request_headers'], true);
        $this->assertSame('[redacted]', $headers['Authorization']);
    }
}
