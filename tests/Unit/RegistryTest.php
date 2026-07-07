<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Projects\Registry;
use Tests\Support\TestCase;

/** Package registry loads projects, permissions, routes, and mutations from manifests. */
final class RegistryTest extends TestCase
{
    /** Dev Tools package loads with correct project view/open permissions. */
    public function test_devtools_package_loaded_with_project_permissions(): void
    {
        $packages = Registry::packages();
        $this->assertArrayHasKey('devtools', $packages);
        $this->assertSame('projects.devtools.view', $packages['devtools']['project']['permissions']['view']);
    }

    /** Dashboard only lists projects the user has view permission for. */
    public function test_visible_projects_requires_view_permission(): void
    {
        $visible = Registry::visibleProjects(['projects.devtools.view']);
        $this->assertCount(1, $visible);
        $this->assertSame('devtools', $visible[0]['id']);
    }

    /** Navbar and project links require open permission, not just view. */
    public function test_openable_projects_requires_open_permission(): void
    {
        $this->assertSame([], Registry::openableProjects(['projects.devtools.view']));
        $openable = Registry::openableProjects(['projects.devtools.open']);
        $this->assertSame('devtools', $openable[0]['id']);
    }

    /** Package manifest permissions merge into the global permission registry. */
    public function test_package_permissions_include_json_formatter(): void
    {
        $names = array_column(Registry::packagePermissions(), 'name');
        $this->assertContains('devtools.formatters.json.use', $names);
        $this->assertContains('devtools.converters.use', $names);
    }

    /** Dev Tools process route is registered for registry coverage checks. */
    public function test_package_mutations_include_devtools_process(): void
    {
        $paths = array_column(Registry::packageMutations(), 'path');
        $this->assertContains('/projects/devtools/process', $paths);
    }
}
