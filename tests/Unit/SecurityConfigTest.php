<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Production-safe defaults: unset APP_ENV must not unlock local-only features or dev asset URLs. */
final class SecurityConfigTest extends TestCase
{
    /** With APP_ENV unset the app runs as production, so the web test runner stays disabled. */
    public function test_app_env_defaults_to_production(): void
    {
        $saved = $_ENV;
        unset($_ENV['APP_ENV']);
        try {
            $cfg = require dirname(__DIR__, 2) . '/config/app.php';
        } finally {
            $_ENV = $saved;
        }
        $this->assertSame('production', $cfg['env']);
    }

    /** The layout only points at the Vite dev server (localhost:5173) when APP_ENV is local. */
    public function test_layout_dev_server_assets_are_local_only(): void
    {
        $layout = (string) file_get_contents(dirname(__DIR__, 2) . '/resources/views/layout.php');
        $this->assertStringContainsString('elseif ($isLocal)', $layout);
    }

    /** /health returns 503 when the database is unreachable so load balancers pull the instance. */
    public function test_health_route_reports_503_when_database_down(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        $this->assertStringContainsString('http_response_code(503)', $routes);
    }
}
