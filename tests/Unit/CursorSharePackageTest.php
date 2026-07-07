<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Projects\Registry;
use PHPUnit\Framework\TestCase;

/** Cursor Share package wiring: manifest, registry merge, and HTTP route table. */
final class CursorSharePackageTest extends TestCase
{
    /** Main UI and API routes are registered for the Cursor Share project. */
    public function test_web_routes_include_cursor_share_endpoints(): void
    {
        /** @var array<string, callable> $routes */
        $routes = require dirname(__DIR__, 2) . '/routes/web.php';
        $this->assertArrayHasKey('GET /projects/cursor-share', $routes);
        $this->assertArrayHasKey('GET /projects/cursor-share/api/state', $routes);
        $this->assertArrayHasKey('POST /projects/cursor-share/posts/create', $routes);
        $this->assertArrayHasKey('GET /projects/cursor-share/download', $routes);
    }

    /** Cursor Share audit event types are documented in the merged registry. */
    public function test_registry_includes_cursor_share_events(): void
    {
        $types = array_column(Registry::packageEvents(), 'type');
        $this->assertContains('cursor-share.post.created', $types);
        $this->assertContains('cursor-share.post.voted', $types);
        $this->assertContains('cursor-share.post.downloaded', $types);
    }

    /** Cursor Share project appears in package registry with browse and post permissions. */
    public function test_manifest_declares_project_and_permissions(): void
    {
        $manifest = Registry::packages()['cursor-share'];
        $this->assertSame('Cursor Share', $manifest['project']['name']);
        $names = array_column($manifest['permissions'] ?? [], 'name');
        $this->assertContains('projects.cursor-share.open', $names);
        $this->assertContains('cursor-share.post', $names);
        $this->assertContains('cursor-share.vote', $names);
    }

    /** Browse UI exposes download and voting controls for community assets. */
    public function test_view_includes_download_and_vote_ui(): void
    {
        $html = file_get_contents(dirname(__DIR__, 2) . '/packages/cursor-share/views/app.php');
        $this->assertIsString($html);
        $this->assertStringContainsString('Download', $html);
        $this->assertStringContainsString('vote(', $html);
        $this->assertStringContainsString('My posts', $html);
    }
}
