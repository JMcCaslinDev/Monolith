<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Session cookie flags and the logout route shape that prevent CSRF logout and cleartext cookies. */
final class SessionSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
    }

    /** Behind a TLS-terminating proxy $_SERVER['HTTPS'] is unset, so an https APP_URL must still mark the cookie secure. */
    public function test_cookie_secure_when_app_url_is_https_even_without_https_server_var(): void
    {
        $this->assertTrue(session_cookie_secure([], 'https://monolith.example.com'));
        $this->assertTrue(session_cookie_secure([], 'HTTPS://monolith.example.com'));
    }

    /** Direct TLS to PHP still marks the cookie secure; plain local http does not. */
    public function test_cookie_secure_follows_https_server_var_and_http_dev(): void
    {
        $this->assertTrue(session_cookie_secure(['HTTPS' => 'on'], 'http://localhost:8000'));
        $this->assertFalse(session_cookie_secure(['HTTPS' => 'off'], 'http://localhost:8000'));
        $this->assertFalse(session_cookie_secure([], 'http://localhost:8000'));
    }

    /** Logout changes state, so it must be POST only (CSRF-checked); a GET link can no longer sign a user out. */
    public function test_logout_is_post_only(): void
    {
        $routes = require dirname(__DIR__, 2) . '/routes/web.php';
        $this->assertArrayHasKey('POST /logout', $routes);
        $this->assertArrayNotHasKey('GET /logout', $routes);
    }

    /** The nav posts a CSRF token to /logout instead of linking to it. */
    public function test_nav_signs_out_with_csrf_form(): void
    {
        $nav = (string) file_get_contents(dirname(__DIR__, 2) . '/resources/views/partials/nav.php');
        $this->assertStringContainsString('action="/logout"', $nav);
        $this->assertStringContainsString('name="csrf"', $nav);
        $this->assertStringNotContainsString('href="/logout"', $nav);
    }

    /** Login regenerates the session id and logout destroys the session. */
    public function test_auth_service_regenerates_on_login_and_destroys_on_logout(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/AuthService.php');
        $this->assertStringContainsString('session_regenerate_id(true)', $src);
        $this->assertStringContainsString('destroy_session()', $src);
    }
}
