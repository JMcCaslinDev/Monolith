<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\RateLimiter;
use PHPUnit\Framework\TestCase;

/** Per-key request throttling used on login and POST routes. */
final class RateLimiterTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/monolith-ratelimit-test-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            unlink($f);
        }
        @rmdir($this->dir);
    }

    /** Hits beyond the limit inside the window are refused, and the first N are allowed. */
    public function test_blocks_after_limit_within_window(): void
    {
        $rl = new RateLimiter($this->dir);
        for ($i = 0; $i < 3; $i++) {
            $this->assertTrue($rl->allow('login:1.2.3.4', 3, 60, 1000));
        }
        $this->assertFalse($rl->allow('login:1.2.3.4', 3, 60, 1001));
    }

    /** A new window resets the counter. */
    public function test_window_expiry_resets(): void
    {
        $rl = new RateLimiter($this->dir);
        $rl->allow('k', 1, 60, 1000);
        $this->assertFalse($rl->allow('k', 1, 60, 1010));
        $this->assertTrue($rl->allow('k', 1, 60, 1061));
    }

    /** Limits are tracked per key, so one noisy client does not block another. */
    public function test_keys_are_independent(): void
    {
        $rl = new RateLimiter($this->dir);
        $rl->allow('a', 1, 60, 1000);
        $this->assertFalse($rl->allow('a', 1, 60, 1001));
        $this->assertTrue($rl->allow('b', 1, 60, 1001));
    }
}
