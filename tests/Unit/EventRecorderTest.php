<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\EventRecorder;
use Tests\Support\TestCase;

/** Audit event recording and per-request correlation for the admin log. */
final class EventRecorderTest extends TestCase
{
    /** State-changing actions persist to the audit log with request context for forensics. */
    public function test_record_persists_event_with_context(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/admin/permissions';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $events = new EventRecorder($this->db);
        $events->record('admin.role.changed', 1, 'user', '2', ['role' => 'member']);

        $rows = $events->recent(1);
        $this->assertCount(1, $rows);
        $this->assertSame('admin.role.changed', $rows[0]['type']);
        $payload = json_decode((string) $rows[0]['payload'], true);
        $this->assertSame('POST', $payload['method']);
        $this->assertSame('/admin/permissions', $payload['path']);
        $this->assertSame('member', $payload['role']);
        $this->assertNotEmpty($rows[0]['correlation_id']);
    }

    /** One correlation ID per request so related events group correctly in the audit log. */
    public function test_correlation_id_stable_within_request(): void
    {
        $a = EventRecorder::correlationId();
        $b = EventRecorder::correlationId();
        $this->assertSame($a, $b);
    }

    /** Paginated recent() returns newest events first with a stable offset window. */
    public function test_recent_paginates_newest_first(): void
    {
        $events = new EventRecorder($this->db);
        for ($i = 1; $i <= 5; $i++) {
            $events->record('action.performed', 1, 'action', (string) $i);
        }

        $this->assertSame(5, $events->count());

        $page1 = $events->recent(2, 0);
        $this->assertCount(2, $page1);
        $this->assertSame('5', $page1[0]['subject_id']);
        $this->assertSame('4', $page1[1]['subject_id']);

        $page2 = $events->recent(2, 2);
        $this->assertCount(2, $page2);
        $this->assertSame('3', $page2[0]['subject_id']);
        $this->assertSame('2', $page2[1]['subject_id']);
    }
}
