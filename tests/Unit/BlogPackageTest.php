<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Projects\Registry;
use PHPUnit\Framework\TestCase;

/** Blog package wiring: manifest, registry merge, and HTTP route table. */
final class BlogPackageTest extends TestCase
{
    /** Editor, API, and public blog routes are registered. */
    public function test_web_routes_include_blog_endpoints(): void
    {
        /** @var array<string, callable> $routes */
        $routes = require dirname(__DIR__, 2) . '/routes/web.php';
        $this->assertArrayHasKey('GET /projects/blog', $routes);
        $this->assertArrayHasKey('GET /projects/blog/api/state', $routes);
        $this->assertArrayHasKey('POST /projects/blog/posts/create', $routes);
        $this->assertArrayHasKey('GET /blog', $routes);
        $this->assertArrayHasKey('GET /blog/sitemap.xml', $routes);
    }

    /** Blog audit event types are documented in the merged registry. */
    public function test_registry_includes_blog_events(): void
    {
        $types = array_column(Registry::packageEvents(), 'type');
        $this->assertContains('blog.post.created', $types);
        $this->assertContains('blog.post.published', $types);
        $this->assertContains('blog.post.viewed', $types);
    }

    /** Blog project declares admin-gated manage and analytics permissions. */
    public function test_manifest_declares_project_and_permissions(): void
    {
        $manifest = Registry::packages()['blog'];
        $this->assertSame('Blog', $manifest['project']['name']);
        $names = array_column($manifest['permissions'] ?? [], 'name');
        $this->assertContains('projects.blog.open', $names);
        $this->assertContains('blog.posts.manage', $names);
        $this->assertContains('blog.analytics.view', $names);
    }

    /** Admin UI exposes drafts, editor, and analytics tabs. */
    public function test_view_includes_editor_and_analytics_ui(): void
    {
        $html = file_get_contents(dirname(__DIR__, 2) . '/packages/blog/views/app.php');
        $this->assertIsString($html);
        $this->assertStringContainsString('Drafts', $html);
        $this->assertStringContainsString('Analytics', $html);
        $this->assertStringContainsString('meta_description', $html);
    }
}
