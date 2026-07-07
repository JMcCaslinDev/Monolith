<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Projects\Registry;
use Devtools\Catalog;
use PHPUnit\Framework\TestCase;

/** Dev Tools package wiring: manifest routes, registry merge, and HTTP route table. */
final class DevtoolsPackageTest extends TestCase
{
    /** Main app and per-tool GET routes are registered so Dev Tools pages resolve. */
    public function test_web_routes_include_devtools_entry_and_process(): void
    {
        /** @var array<string, callable> $routes */
        $routes = require dirname(__DIR__, 2) . '/routes/web.php';
        $this->assertArrayHasKey('GET /projects/devtools', $routes);
        $this->assertArrayHasKey('POST /projects/devtools/process', $routes);
    }

    /** Every catalog tool has a deep-link route so sidebar navigation matches the route table. */
    public function test_web_routes_include_each_tool_slug(): void
    {
        /** @var array<string, callable> $routes */
        $routes = require dirname(__DIR__, 2) . '/routes/web.php';
        foreach (Catalog::tools() as $tool) {
            $key = 'GET /projects/devtools/' . $tool['slug'];
            $this->assertArrayHasKey($key, $routes, 'Missing route for ' . $tool['slug']);
        }
    }

    /** Manifest generates one GET route per tool plus hub and legacy redirects. */
    public function test_manifest_route_count_matches_catalog(): void
    {
        $manifest = Registry::packages()['devtools'];
        $routes = $manifest['routes'] ?? [];
        $this->assertCount(1 + count(Catalog::tools()) + 3, $routes);
    }

    /** Dev Tools audit event types are documented in the merged registry. */
    public function test_registry_includes_devtools_events(): void
    {
        $types = array_column(Registry::packageEvents(), 'type');
        $this->assertContains('devtools.tool.opened', $types);
        $this->assertContains('devtools.tool.used', $types);
    }

    /** Permission count covers project access, categories, and each individual tool. */
    public function test_manifest_permission_count_matches_catalog(): void
    {
        $manifest = Registry::packages()['devtools'];
        $expected = 2 + count(Catalog::categories()) + count(Catalog::tools());
        $this->assertCount($expected, $manifest['permissions'] ?? []);
    }

    /** Sidebar collapse logic keeps category open state consistent when toggling sections. */
    public function test_sidebar_collapse_logic_script_passes(): void
    {
        $script = dirname(__DIR__, 2) . '/scripts/check-devtools-sidebar.mjs';
        $cmd = 'node ' . escapeshellarg($script);
        exec($cmd, $output, $code);
        $this->assertSame(0, $code, implode("\n", $output));
    }

    /** Sidebar template binds tool lists to openCategories so collapsed sections hide their tools. */
    public function test_sidebar_template_wires_collapse_visibility(): void
    {
        $html = file_get_contents(dirname(__DIR__, 2) . '/packages/devtools/views/app.php');
        $this->assertIsString($html);
        $this->assertStringContainsString('x-show="openCategories[', $html);
        $this->assertStringContainsString('@click="toggleCategory(', $html);
    }

    /** Dev Tools shell uses fixed-height panes so sidebar and main content scroll independently. */
    public function test_devtools_shell_scroll_layout(): void
    {
        $html = file_get_contents(dirname(__DIR__, 2) . '/packages/devtools/views/app.php');
        $this->assertIsString($html);
        $this->assertStringContainsString('devtools-shell', $html);
        $this->assertStringContainsString('devtools-sidebar', $html);
        $this->assertStringContainsString('x-ref="mainPanel"', $html);
        $this->assertStringContainsString('overflow: hidden', $html);
    }

    /** QR tool uses a standards-compliant encoder so phone cameras can scan generated codes. */
    public function test_qr_encoder_script_passes(): void
    {
        $script = dirname(__DIR__, 2) . '/scripts/check-devtools-qr.mjs';
        $cmd = 'node ' . escapeshellarg($script);
        exec($cmd, $output, $code);
        $this->assertSame(0, $code, implode("\n", $output));
    }

    /** Image converter detects upload MIME, adds placeholder formats, and gates canvas export. */
    public function test_image_converter_format_script_passes(): void
    {
        $script = dirname(__DIR__, 2) . '/scripts/check-devtools-image-converter.mjs';
        $cmd = 'node ' . escapeshellarg($script);
        exec($cmd, $output, $code);
        $this->assertSame(0, $code, implode("\n", $output));
    }

    /** Color blindness simulator uses valid matrices and logs user actions for audit. */
    public function test_color_blindness_script_passes(): void
    {
        $script = dirname(__DIR__, 2) . '/scripts/check-devtools-color-blindness.mjs';
        exec('node ' . escapeshellarg($script), $output, $code);
        $this->assertSame(0, $code, implode("\n", $output));
    }
}
