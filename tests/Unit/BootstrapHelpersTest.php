<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Bootstrap helpers for audit grouping, summaries, admin access, and user-facing timestamps. */
final class BootstrapHelpersTest extends TestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
    }

    /** Groups audit events from the same HTTP request so the admin log shows related activity together. */
    public function test_group_events_clusters_by_correlation_id(): void
    {
        $groups = group_events([
            ['id' => 1, 'correlation_id' => 'abc', 'type' => 'page.viewed', 'created_at' => '2026-01-01', 'payload' => '{}'],
            ['id' => 2, 'correlation_id' => 'abc', 'type' => 'permission.granted', 'created_at' => '2026-01-01', 'payload' => '{}'],
        ]);
        $this->assertCount(1, $groups);
        $this->assertSame(1, $groups[0]['related_count']);
    }

    /** Turns raw admin.grant.added events into readable audit summaries with permission and user. */
    public function test_event_summary_humanizes_admin_grant(): void
    {
        $summary = event_summary([
            'type' => 'admin.grant.added',
            'subject_id' => '2',
            'payload' => json_encode(['permission' => 'devtools.formatters.json.use']),
        ]);
        $this->assertStringContainsString('devtools.formatters.json.use', $summary);
        $this->assertStringContainsString('#2', $summary);
    }

    /** Admin hub access requires at least one admin permission — prevents a single role string bypass. */
    public function test_has_admin_access_composite(): void
    {
        $this->assertTrue(has_admin_access(['admin.events.view']));
        $this->assertFalse(has_admin_access(['pages.dashboard.view']));
    }

    /** Admin navbar link is suppressed when the user lacks any admin permission. */
    public function test_navbar_admin_hidden_without_admin_access(): void
    {
        $this->assertFalse(navbar_admin_visible(1, false));
    }

    /** Dev Tools opened events show which tool the user navigated to in the audit log. */
    public function test_event_summary_devtools_tool_opened(): void
    {
        $summary = event_summary([
            'type' => 'devtools.tool.opened',
            'subject_id' => 'json',
            'payload' => json_encode(['tool' => 'json', 'category' => 'formatters']),
        ]);
        $this->assertStringContainsString('json', $summary);
    }

    /** Dev Tools server actions appear in the audit log with tool and action name. */
    public function test_event_summary_devtools_tool_used(): void
    {
        $summary = event_summary([
            'type' => 'devtools.tool.used',
            'payload' => json_encode(['tool' => 'jwt', 'action' => 'run']),
        ]);
        $this->assertStringContainsString('jwt', $summary);
        $this->assertStringContainsString('run', $summary);
    }

    /** Dev Tools field focus events show which control was used without logging typed content. */
    public function test_event_summary_devtools_focus_action(): void
    {
        $summary = event_summary([
            'type' => 'action.performed',
            'payload' => json_encode(['action' => 'devtools.json.focus', 'field' => 'textarea']),
        ]);
        $this->assertStringContainsString('json.focus', $summary);
        $this->assertStringContainsString('[textarea]', $summary);
    }

    /** Dev Tools sidebar category toggles appear in the audit log with category id. */
    public function test_event_summary_devtools_sidebar_category(): void
    {
        $summary = event_summary([
            'type' => 'action.performed',
            'payload' => json_encode(['action' => 'devtools.sidebar.category.open', 'category' => 'encoders']),
        ]);
        $this->assertStringContainsString('sidebar.category.open', $summary);
        $this->assertStringContainsString('(encoders)', $summary);
    }

    /** Relative and timezone-formatted timestamps render correctly on status and audit pages. */
    public function test_time_ago_and_format_user_datetime(): void
    {
        $iso = (new \DateTimeImmutable('-3 minutes', new \DateTimeZone('UTC')))->format('c');
        $this->assertSame('3 minutes ago', time_ago($iso));

        $utc = '2026-07-07T05:20:01+00:00';
        $formatted = format_user_datetime($utc, 0);
        $this->assertStringContainsString('2026', $formatted);
        $this->assertStringNotContainsString('+00:00', $formatted);
    }

    /** Naive DB timestamps (no TZ suffix) are treated as UTC so audit "ago" matches wall clock. */
    public function test_parse_utc_datetime_treats_naive_db_values_as_utc(): void
    {
        $dt = parse_utc_datetime('2026-07-07 05:00:00');
        $this->assertSame('05', $dt->format('H'));
        $this->assertSame(0, $dt->getOffset());
    }

    /** Chrome DevTools /.well-known/ probes are not logged as user 404s. */
    public function test_should_record_page_event_skips_well_known_probes(): void
    {
        $this->assertFalse(should_record_page_event('/.well-known/appspecific/com.chrome.devtools.json'));
        $this->assertTrue(should_record_page_event('/admin/events'));
        $this->assertFalse(should_record_page_event('/health'));
    }
}
