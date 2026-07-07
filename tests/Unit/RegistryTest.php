<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Projects\Registry;
use Tests\Support\TestCase;

final class RegistryTest extends TestCase
{
    public function test_tools_package_loaded_with_project_permissions(): void
    {
        $packages = Registry::packages();
        $this->assertArrayHasKey('tools', $packages);
        $this->assertSame('projects.tools.view', $packages['tools']['project']['permissions']['view']);
    }

    public function test_visible_projects_requires_view_permission(): void
    {
        $visible = Registry::visibleProjects(['projects.tools.view']);
        $this->assertCount(1, $visible);
        $this->assertSame('tools', $visible[0]['id']);
    }

    public function test_openable_projects_requires_open_permission(): void
    {
        $this->assertSame([], Registry::openableProjects(['projects.tools.view']));
        $openable = Registry::openableProjects(['projects.tools.open']);
        $this->assertSame('tools', $openable[0]['id']);
    }

    public function test_package_permissions_include_json_converter(): void
    {
        $names = array_column(Registry::packagePermissions(), 'name');
        $this->assertContains('tools.json-converter.use', $names);
    }
}
