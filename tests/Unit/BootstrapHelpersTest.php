<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class BootstrapHelpersTest extends TestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
    }

    public function test_group_events_clusters_by_correlation_id(): void
    {
        $groups = group_events([
            ['id' => 1, 'correlation_id' => 'abc', 'type' => 'page.viewed', 'created_at' => '2026-01-01', 'payload' => '{}'],
            ['id' => 2, 'correlation_id' => 'abc', 'type' => 'permission.granted', 'created_at' => '2026-01-01', 'payload' => '{}'],
        ]);
        $this->assertCount(1, $groups);
        $this->assertSame(1, $groups[0]['related_count']);
    }

    public function test_event_summary_humanizes_admin_grant(): void
    {
        $summary = event_summary([
            'type' => 'admin.grant.added',
            'subject_id' => '2',
            'payload' => json_encode(['permission' => 'tools.json-converter.use']),
        ]);
        $this->assertStringContainsString('tools.json-converter.use', $summary);
        $this->assertStringContainsString('#2', $summary);
    }

    public function test_has_admin_access_composite(): void
    {
        $this->assertTrue(has_admin_access(['admin.events.view']));
        $this->assertFalse(has_admin_access(['pages.dashboard.view']));
    }
}
