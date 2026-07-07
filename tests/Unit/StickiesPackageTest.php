<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Projects\Registry;
use PHPUnit\Framework\TestCase;

/** Stickies package wiring: manifest, registry merge, routes, and board UI. */
final class StickiesPackageTest extends TestCase
{
    /** Stickies routes are registered for board page and JSON API. */
    public function test_web_routes_include_stickies_endpoints(): void
    {
        /** @var array<string, callable> $routes */
        $routes = require dirname(__DIR__, 2) . '/routes/web.php';
        $this->assertArrayHasKey('GET /projects/stickies', $routes);
        $this->assertArrayHasKey('GET /projects/stickies/api/notes', $routes);
        $this->assertArrayHasKey('POST /projects/stickies/note/save', $routes);
        $this->assertArrayHasKey('POST /projects/stickies/note/move', $routes);
        $this->assertArrayHasKey('POST /projects/stickies/note/delete', $routes);
    }

    /** Stickies audit event types are documented in the merged registry. */
    public function test_registry_includes_stickies_events(): void
    {
        $types = array_column(Registry::packageEvents(), 'type');
        $this->assertContains('stickies.note.saved', $types);
        $this->assertContains('stickies.note.deleted', $types);
        $this->assertContains('stickies.note.moved', $types);
    }

    /** Stickies project declares view, open, and manage permissions. */
    public function test_manifest_declares_project_and_permissions(): void
    {
        $manifest = Registry::packages()['stickies'];
        $this->assertSame('Stickies', $manifest['project']['name']);
        $names = array_column($manifest['permissions'] ?? [], 'name');
        $this->assertContains('projects.stickies.open', $names);
        $this->assertContains('stickies.manage', $names);
    }

    /** Board UI exposes search, category filter, flip settings, and fullscreen editor. */
    public function test_view_includes_board_search_and_editor_ui(): void
    {
        $html = file_get_contents(dirname(__DIR__, 2) . '/packages/stickies/views/app.php');
        $this->assertIsString($html);
        $this->assertStringContainsString('stickiesApp', $html);
        $this->assertStringContainsString('stickies-search', $html);
        $this->assertStringContainsString('sticky-flip', $html);
        $this->assertStringContainsString('expandedId', $html);
    }
}
