<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

final class CheckScriptsTest extends TestCase
{
    public function test_check_coverage_script_passes(): void
    {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(
            dirname(__DIR__, 2) . '/scripts/check-coverage.php'
        );
        exec($cmd, $output, $code);
        $this->assertSame(0, $code, implode("\n", $output));
    }
}
