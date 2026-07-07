<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\TestStatusReader;
use PHPUnit\Framework\TestCase;

/** Parses PHPUnit JUnit output for the admin system status page. */
final class TestStatusReaderTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir() . '/monolith-test-status-' . uniqid('', true);
        mkdir($this->tmp . '/var', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->rmTree($this->tmp);
    }

    /** JUnit XML is parsed into suite tree with pass/fail counts for the admin status page. */
    public function test_read_tree_parses_junit_suites_and_counts(): void
    {
        file_put_contents($this->tmp . '/var/test-status.json', json_encode([
            'ran_at' => '2026-01-01T00:00:00+00:00',
            'passed' => true,
            'tests' => 0,
            'failures' => 0,
            'errors' => 0,
            'coverage_percent' => null,
            'coverage_available' => false,
        ]));
        file_put_contents($this->tmp . '/var/test-results.junit.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite name="Monolith" tests="3" failures="1" errors="0">
    <testsuite name="Tests\Unit\FooTest" file="/tmp/FooTest.php" tests="2" failures="1" errors="0">
      <testcase name="test_one_passes" classname="Tests.Unit.FooTest"/>
      <testcase name="test_two_fails" classname="Tests.Unit.FooTest">
        <failure type="">boom</failure>
      </testcase>
    </testsuite>
    <testsuite name="Tests\Integration\BarTest" file="/tmp/BarTest.php" tests="1" failures="0" errors="0">
      <testcase name="test_ok" classname="Tests.Integration.BarTest"/>
    </testsuite>
  </testsuite>
</testsuites>
XML);

        $tree = (new TestStatusReader($this->tmp))->readTree();

        $this->assertSame(3, $tree['totals']['tests']);
        $this->assertSame(2, $tree['totals']['passed']);
        $this->assertSame(1, $tree['totals']['failed']);
        $this->assertFalse($tree['status']['passed']);
        $this->assertSame('Foo', $tree['suites'][0]['label']);
        $this->assertSame('one_passes', $tree['suites'][0]['tests'][0]['name']);
        $this->assertSame('passed', $tree['suites'][0]['tests'][0]['status']);
        $this->assertSame('failed', $tree['suites'][0]['tests'][1]['status']);
        $this->assertSame('boom', $tree['suites'][0]['tests'][1]['detail']);
        $this->assertNull($tree['suites'][0]['tests'][0]['detail']);
        $this->assertSame('Bar', $tree['suites'][1]['label']);
    }

    /** Docblocks above test methods become descriptions shown in the status graph UI. */
    public function test_parse_test_descriptions_from_docblocks(): void
    {
        $testFile = $this->tmp . '/SampleTest.php';
        file_put_contents($testFile, <<<'PHP'
<?php
/** Sample suite for status UI. */
final class SampleTest {
    /** Ensures foo bars correctly for security. */
    public function test_foo_bars(): void {}
}
PHP);
        file_put_contents($this->tmp . '/var/test-results.junit.xml', <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<testsuites><testsuite name="Tests\Unit\SampleTest" file="{$testFile}" tests="1">
  <testcase name="test_foo_bars"/>
</testsuite></testsuites>
XML);

        $tree = (new TestStatusReader($this->tmp))->readTree();
        $this->assertSame('Sample suite for status UI.', $tree['suites'][0]['description']);
        $this->assertSame('Ensures foo bars correctly for security.', $tree['suites'][0]['tests'][0]['description']);
    }

    private function rmTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->rmTree($path) : unlink($path);
        }
        rmdir($dir);
    }
}
