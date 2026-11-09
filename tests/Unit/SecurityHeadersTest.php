<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Browser hardening headers sent with every page. */
final class SecurityHeadersTest extends TestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
    }

    /** @param list<string> $headers */
    private function header(array $headers, string $name): ?string
    {
        foreach ($headers as $h) {
            if (str_starts_with($h, $name . ': ')) {
                return substr($h, strlen($name) + 2);
            }
        }
        return null;
    }

    /** Production blocks framing, MIME sniffing and foreign scripts, and never allows the Vite dev origin. */
    public function test_production_headers_lock_down_framing_and_scripts(): void
    {
        $h = security_headers('production', 'https://monolith.example.com', 'abc123');
        $csp = (string) $this->header($h, 'Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("'nonce-abc123'", $csp);
        $this->assertStringNotContainsString('localhost:5173', $csp);
        $this->assertSame('DENY', $this->header($h, 'X-Frame-Options'));
        $this->assertSame('nosniff', $this->header($h, 'X-Content-Type-Options'));
        $this->assertNotNull($this->header($h, 'Referrer-Policy'));
        $this->assertNotNull($this->header($h, 'Strict-Transport-Security'));
    }

    /** Local development may load Vite assets from its dev server, and HSTS is not sent over plain http. */
    public function test_local_allows_vite_origin_and_skips_hsts_on_http(): void
    {
        $h = security_headers('local', 'http://localhost:8000', 'n');
        $this->assertStringContainsString('http://localhost:5173', (string) $this->header($h, 'Content-Security-Policy'));
        $this->assertNull($this->header($h, 'Strict-Transport-Security'));
    }

    /** The inline theme script carries the request nonce so the CSP does not need unsafe-inline for scripts. */
    public function test_layout_inline_script_uses_nonce(): void
    {
        $layout = (string) file_get_contents(dirname(__DIR__, 2) . '/resources/views/layout.php');
        $this->assertStringContainsString('<script nonce="<?= htmlspecialchars(csp_nonce()', $layout);
    }
}
