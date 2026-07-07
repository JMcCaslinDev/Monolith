#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$varDir = $root . '/var';
$coverageDir = $varDir . '/coverage';

foreach ([$varDir, $coverageDir] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

$phpunit = $root . '/vendor/bin/phpunit';
if (!is_file($phpunit)) {
    fwrite(STDERR, "Run: composer install\n");
    exit(1);
}

$hasCoverage = extension_loaded('pcov') || extension_loaded('xdebug');
$args = ['--configuration', $root . '/phpunit.xml'];
if ($hasCoverage) {
    $args[] = '--coverage-clover';
    $args[] = $coverageDir . '/clover.xml';
    $args[] = '--coverage-text';
    $args[] = '--coverage-filter';
    $args[] = $root . '/app';
} else {
    fwrite(STDERR, "Note: install pcov or xdebug for coverage reports\n");
}

$cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($phpunit) . ' ' . implode(' ', array_map('escapeshellarg', $args));
passthru($cmd, $exitCode);

$junitPath = $varDir . '/test-results.junit.xml';
$summary = [
    'ran_at' => gmdate('c'),
    'exit_code' => $exitCode,
    'passed' => false,
    'tests' => 0,
    'failures' => 0,
    'errors' => 0,
    'coverage_percent' => null,
    'coverage_available' => $hasCoverage,
];

if (is_file($junitPath)) {
    $xml = simplexml_load_file($junitPath);
    if ($xml !== false) {
        $summary['tests'] = (int) ($xml['tests'] ?? 0);
        $summary['failures'] = (int) ($xml['failures'] ?? 0);
        $summary['errors'] = (int) ($xml['errors'] ?? 0);
        $summary['passed'] = $exitCode === 0;
    }
}

if ($hasCoverage && is_file($coverageDir . '/clover.xml')) {
    $clover = simplexml_load_file($coverageDir . '/clover.xml');
    if ($clover !== false) {
        $metrics = $clover->project->metrics;
        $statements = (int) ($metrics['statements'] ?? 0);
        $covered = (int) ($metrics['coveredstatements'] ?? 0);
        $summary['coverage_percent'] = $statements > 0
            ? round(($covered / $statements) * 100, 1)
            : 0.0;
    }
}

file_put_contents(
    $varDir . '/test-status.json',
    json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
);

exit($summary['passed'] ? 0 : ($exitCode !== 0 ? $exitCode : 1));
