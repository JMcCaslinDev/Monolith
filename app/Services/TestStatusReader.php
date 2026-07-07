<?php

declare(strict_types=1);

namespace App\Services;

final class TestStatusReader
{
    private string $statusPath;
    private string $root;

    public function __construct(?string $root = null)
    {
        $this->root = $root ?? dirname(__DIR__, 2);
        $this->statusPath = $this->root . '/var/test-status.json';
    }

  /** @return array<string, mixed> */
    public function read(): array
    {
        if (!is_file($this->statusPath)) {
            return [
            'ran_at' => null,
            'passed' => null,
            'tests' => 0,
            'failures' => 0,
            'errors' => 0,
            'coverage_percent' => null,
            'coverage_available' => extension_loaded('pcov') || extension_loaded('xdebug'),
            'message' => 'No test run yet. Run: composer test',
            ];
        }
        $data = json_decode((string) file_get_contents($this->statusPath), true);
        return is_array($data) ? $data : [];
    }

  /** @return array{status: array<string, mixed>, suites: list<array{label: string, description: string|null, tests: list<array{name: string, status: string, detail: string|null, description: string|null}>}>, totals: array{tests: int, passed: int, failed: int, errors: int}} */
    public function readTree(): array
    {
        $status = $this->read();
        $suites = $this->parseJunitSuites();
        $totals = ['tests' => 0, 'passed' => 0, 'failed' => 0, 'errors' => 0];
        foreach ($suites as $suite) {
            foreach ($suite['tests'] as $test) {
                $totals['tests']++;
                $totals[$test['status'] === 'passed' ? 'passed' : ($test['status'] === 'error' ? 'errors' : 'failed')]++;
            }
        }
        if ($totals['tests'] > 0) {
            $status['tests'] = $totals['tests'];
            $status['failures'] = $totals['failed'];
            $status['errors'] = $totals['errors'];
            $status['passed'] = $totals['failed'] === 0 && $totals['errors'] === 0;
        }
        return ['status' => $status, 'suites' => $suites, 'totals' => $totals];
    }

  /** @return list<array{label: string, description: string|null, tests: list<array{name: string, status: string, detail: string|null, description: string|null}>}> */
    private function parseJunitSuites(): array
    {
        $junitPath = $this->root . '/var/test-results.junit.xml';
        if (!is_file($junitPath)) {
            return [];
        }
        $xml = simplexml_load_file($junitPath);
        if ($xml === false) {
            return [];
        }
        $suites = [];
        foreach ($xml->xpath('//testsuite[@file]') as $suite) {
            $file = (string) $suite['file'];
            $meta = $this->parseFileDescriptions($file);
            $tests = [];
            foreach ($suite->testcase as $case) {
                $method = (string) $case['name'];
                $status = 'passed';
                $detail = null;
                if (isset($case->failure)) {
                    $status = 'failed';
                    $detail = trim((string) $case->failure);
                } elseif (isset($case->error)) {
                    $status = 'error';
                    $detail = trim((string) $case->error);
                }
                $tests[] = [
                'name' => $this->shortTestName($method),
                'status' => $status,
                'detail' => $detail !== '' ? $detail : null,
                'description' => $meta['tests'][$method] ?? null,
                ];
            }
            if ($tests === []) {
                continue;
            }
            $suites[] = [
            'label' => $this->shortSuiteName((string) $suite['name']),
            'description' => $meta['suite'],
            'tests' => $tests,
            ];
        }
        return $suites;
    }

    private function shortSuiteName(string $fqn): string
    {
        $short = preg_replace('/^Tests\\\\(Unit|Integration)\\\\/', '', $fqn) ?? $fqn;
        return str_ends_with($short, 'Test') ? substr($short, 0, -4) : $short;
    }

    private function shortTestName(string $name): string
    {
        return str_starts_with($name, 'test_') ? substr($name, 5) : $name;
    }

  /** @return array{suite: string|null, tests: array<string, string>} */
    private function parseFileDescriptions(string $file): array
    {
        if (!is_file($file)) {
            return ['suite' => null, 'tests' => []];
        }
        $src = (string) file_get_contents($file);
        $suite = null;
        if (preg_match('/\/\*\*((?:[^*]|\*(?!\/))*)\*\/\s*(?:final\s+)?class\s+/s', $src, $classMatch)) {
            $text = $this->normalizeDocblock($classMatch[1]);
            $suite = $text !== '' ? $text : null;
        }
        $tests = [];
        if (
            preg_match_all(
                '/\/\*\*((?:[^*]|\*(?!\/))*)\*\/\s*(?:public\s+)?function\s+(test_\w+)/s',
                $src,
                $matches,
                PREG_SET_ORDER
            )
        ) {
            foreach ($matches as $match) {
                $text = $this->normalizeDocblock($match[1]);
                if ($text !== '') {
                    $tests[$match[2]] = $text;
                }
            }
        }
        return ['suite' => $suite, 'tests' => $tests];
    }

    private function normalizeDocblock(string $raw): string
    {
        $text = trim(preg_replace('/^\s*\*\s?/m', '', $raw));
        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    public function canRunFromWeb(): bool
    {
        return (config('app')['env'] ?? '') === 'local';
    }

    public function run(): int
    {
        if (!$this->canRunFromWeb()) {
            return 1;
        }
        $script = $this->root . '/scripts/run-tests.php';
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1';
        passthru($cmd, $exitCode);
        return $exitCode;
    }

  /** @return list<array{name: string, ok: bool, output: string}> */
    public function runHealthChecks(): array
    {
        $checks = [
        'check-registry.php',
        'check-permissions.php',
        'check-coverage.php',
        ];
        $results = [];
        foreach ($checks as $script) {
            $path = $this->root . '/scripts/' . $script;
            $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($path) . ' 2>&1';
            exec($cmd, $output, $code);
            $results[] = [
            'name' => $script,
            'ok' => $code === 0,
            'output' => trim(implode("\n", $output)),
            ];
        }
        return $results;
    }
}
