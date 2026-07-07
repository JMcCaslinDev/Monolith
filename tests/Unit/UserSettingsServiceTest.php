<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\UserSettingsService;
use Tests\Support\TestCase;

final class UserSettingsServiceTest extends TestCase
{
    public function test_set_and_get_roundtrip(): void
    {
        $settings = new UserSettingsService($this->db);
        $settings->set(1, 'navbar_projects', ['tools', 'billing']);
        $this->assertSame(['tools', 'billing'], $settings->get(1, 'navbar_projects'));
    }

    public function test_navbar_defaults_to_all_openable_when_unset(): void
    {
        $settings = new UserSettingsService($this->db);
        $this->assertSame(['tools', 'billing', 'crm'], $settings->navbarProjectIds(1, ['tools', 'billing', 'crm']));
    }

    public function test_navbar_filters_to_saved_pins_when_set(): void
    {
        $settings = new UserSettingsService($this->db);
        $settings->set(1, 'navbar_projects', ['tools', 'removed-project']);
        $this->assertSame(['tools'], $settings->navbarProjectIds(1, ['tools', 'billing']));
    }
}
