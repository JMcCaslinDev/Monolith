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
