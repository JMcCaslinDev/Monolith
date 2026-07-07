<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\EventRecorder;
use Tests\Support\TestCase;

final class EventRecorderTest extends TestCase
{
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

    public function test_correlation_id_stable_within_request(): void
    {
        $a = EventRecorder::correlationId();
        $b = EventRecorder::correlationId();
        $this->assertSame($a, $b);
    }
}
