<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\UserSettingsService;
use Tests\Support\TestCase;

/** Per-user settings such as navbar pins and timezone display preferences. */
final class UserSettingsServiceTest extends TestCase
{
    /** User preferences persist and round-trip from the database. */
    public function test_set_and_get_roundtrip(): void
    {
        $settings = new UserSettingsService($this->db);
        $settings->set(1, 'navbar_projects', ['devtools', 'billing']);
        $this->assertSame(['devtools', 'billing'], $settings->get(1, 'navbar_projects'));
    }

    /** New users see all openable projects in the navbar until they pin a custom set. */
    public function test_navbar_defaults_to_all_openable_when_unset(): void
    {
        $settings = new UserSettingsService($this->db);
        $this->assertSame(['devtools', 'billing', 'crm'], $settings->navbarProjectIds(1, ['devtools', 'billing', 'crm']));
    }

    /** Saved navbar pins are filtered to projects the user can still open. */
    public function test_navbar_filters_to_saved_pins_when_set(): void
    {
        $settings = new UserSettingsService($this->db);
        $settings->set(1, 'navbar_projects', ['devtools', 'removed-project']);
        $this->assertSame(['devtools'], $settings->navbarProjectIds(1, ['devtools', 'billing']));
    }

    /** Timezone preference persists for per-user timestamp display. */
    public function test_timezone_roundtrip(): void
    {
        $settings = new UserSettingsService($this->db);
        $settings->set(1, 'timezone', 'America/New_York');
        $this->assertSame('America/New_York', $settings->get(1, 'timezone'));
    }

    /** Admin navbar pin can be turned off and persists per user. */
    public function test_navbar_admin_pin_roundtrip(): void
    {
        $settings = new UserSettingsService($this->db);
        $settings->set(1, 'navbar_admin', false);
        $this->assertFalse($settings->get(1, 'navbar_admin'));
        $settings->set(1, 'navbar_admin', true);
        $this->assertTrue($settings->get(1, 'navbar_admin'));
    }
}
