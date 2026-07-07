<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

/** CI-style scripts that verify registry and documentation stay in sync. */
final class CheckScriptsTest extends TestCase
{
    /** Registry coverage script passes — routes and events stay documented in config/registry.php. */
    public function test_check_coverage_script_passes(): void
    {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(
            dirname(__DIR__, 2) . '/scripts/check-coverage.php'
        );
        exec($cmd, $output, $code);
        $this->assertSame(0, $code, implode("\n", $output));
    }
}
