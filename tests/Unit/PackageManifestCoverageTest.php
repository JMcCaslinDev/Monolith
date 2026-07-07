<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Projects\Registry;
use PHPUnit\Framework\TestCase;

/** Every package manifest stays aligned with routes, permissions, and registry merge. */
final class PackageManifestCoverageTest extends TestCase
{
    /** Each package permission is merged into the global registry for grants and checks. */
    public function test_package_permissions_appear_in_merged_registry(): void
    {
        $merged = array_column(Registry::packagePermissions(), 'name');
        foreach (Registry::packages() as $id => $pkg) {
            foreach ($pkg['permissions'] ?? [] as $perm) {
                $name = is_array($perm) ? ($perm['name'] ?? '') : '';
                $this->assertContains(
                    $name,
                    $merged,
                    "Package {$id} permission {$name} missing from merged registry",
                );
            }
        }
    }

    /** Each package event type is documented in the merged registry for audit coverage. */
    public function test_package_events_appear_in_merged_registry(): void
    {
        $merged = array_column(Registry::packageEvents(), 'type');
        foreach (Registry::packages() as $id => $pkg) {
            foreach ($pkg['events'] ?? [] as $event) {
                $type = is_array($event) ? ($event['type'] ?? '') : '';
                $this->assertContains(
                    $type,
                    $merged,
                    "Package {$id} event {$type} missing from merged registry",
                );
            }
        }
    }

    /** Package page routes and POST mutations resolve to registered HTTP handlers. */
    public function test_package_routes_and_mutations_are_registered(): void
    {
        /** @var array<string, callable> $routes */
        $routes = require dirname(__DIR__, 2) . '/routes/web.php';
        foreach (Registry::packageRoutes() as $route) {
            $key = ($route['method'] ?? 'GET') . ' ' . ($route['path'] ?? '');
            $this->assertArrayHasKey($key, $routes, "Missing route {$key}");
        }
        foreach (Registry::packageMutations() as $mutation) {
            $key = ($mutation['method'] ?? 'POST') . ' ' . ($mutation['path'] ?? '');
            $this->assertArrayHasKey($key, $routes, "Missing mutation {$key}");
        }
    }

    /** Every project declares distinct view and open permissions for dashboard gating. */
    public function test_each_project_declares_view_and_open_permissions(): void
    {
        foreach (Registry::projects() as $project) {
            $id = $project['id'] ?? '?';
            $view = $project['permissions']['view'] ?? '';
            $open = $project['permissions']['open'] ?? '';
            $this->assertNotSame('', $view, "Project {$id} missing view permission");
            $this->assertNotSame('', $open, "Project {$id} missing open permission");
            $this->assertNotSame($view, $open, "Project {$id} view/open should differ");
        }
    }
}
