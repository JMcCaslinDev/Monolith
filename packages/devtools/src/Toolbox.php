<?php

declare(strict_types=1);

namespace Devtools;

use DOMDocument;
use InvalidArgumentException;
use JsonException;
use Throwable;

final class Toolbox
{
    private const UUID_BATCH = 5;

    /** @param array<string, mixed> $params */
    public static function process(string $slug, string $action, array $params = []): array
    {
        $input = (string) ($params['input'] ?? '');
        $extra = is_array($params['extra'] ?? null) ? $params['extra'] : [];

        return match ($slug) {
            'cron-parser' => self::cronParser($action, $input),
            'date' => self::dateConverter($action, $input, $extra),
            'json-table' => self::jsonTable($action, $input),
            'json-yaml' => self::jsonYaml($action, $input),
            'number-base' => self::numberBase($action, $input, $extra),
            'base64-text' => self::base64Text($action, $input),
            'base64-image' => self::base64Image($action, $input),
            'certificate' => self::certificate($input),
            'gzip' => self::gzip($action, $input),
            'html' => self::htmlCodec($action, $input),
            'jwt' => self::jwt($input),
            'url' => self::urlCodec($action, $input),
            'json' => self::jsonFormat($action, $input),
            'sql' => self::sqlFormat($action, $input),
            'xml' => self::xmlFormat($action, $input),
            'hash' => self::hashGen($input, $extra),
            'lorem-ipsum' => self::loremIpsum($extra),
            'password' => self::password($extra),
            'uuid' => self::uuid($extra),
            'jsonpath' => self::jsonPath($input, (string) ($extra['path'] ?? '$')),
            'regex' => self::regexTest($input, (string) ($extra['pattern'] ?? ''), $extra),
            'xml-tester' => self::xmlTester($action, $input, (string) ($extra['xpath'] ?? '')),
            'escape-unescape' => self::escapeUnescape($action, $input, (string) ($extra['mode'] ?? 'json')),
            'list-compare' => self::listCompare($input, (string) ($params['input_b'] ?? '')),
            'markdown-preview' => self::markdownPreview($input),
            'text-analyzer' => self::textAnalyzer($input, $action),
            'text-compare' => self::textCompare($input, (string) ($params['input_b'] ?? '')),
            default => throw new InvalidArgumentException('Unknown tool: ' . $slug),
        };
    }

    /** @return array{output: string, meta?: array<string, mixed>, error?: string} */
    private static function ok(string $output, array $meta = []): array
    {
        return ['output' => $output, 'meta' => $meta];
    }

    /** @return array{output: string, error: string} */
    private static function err(string $message): array
    {
        return ['output' => '', 'error' => $message];
    }

    private static function cronParser(string $action, string $input): array
    {
        $expr = trim($input);
        if ($expr === '') {
            return self::err('Enter a cron expression');
        }
        if ($action === 'describe') {
            try {
                return self::ok(self::describeCron($expr));
            } catch (InvalidArgumentException $e) {
                return self::err($e->getMessage());
            }
        }
        return self::err('Unknown action');
    }

    private static function describeCron(string $expr): string
    {
        $parts = preg_split('/\s+/', trim($expr)) ?: [];
        if (count($parts) !== 5 && count($parts) !== 6) {
            throw new InvalidArgumentException('Cron must have 5 or 6 fields');
        }
        $labels = count($parts) === 6
            ? ['second', 'minute', 'hour', 'day of month', 'month', 'day of week']
            : ['minute', 'hour', 'day of month', 'month', 'day of week'];
        $lines = [];
        foreach ($parts as $i => $field) {
            $lines[] = ucfirst($labels[$i]) . ': ' . self::describeCronField($field, $labels[$i]);
        }
        return implode("\n", $lines);
    }

    private static function describeCronField(string $field, string $label): string
    {
        if ($field === '*') {
            return 'every ' . $label;
        }
        if (str_starts_with($field, '*/')) {
            return 'every ' . substr($field, 2) . ' ' . $label . '(s)';
        }
        if (str_contains($field, '-')) {
            return 'from ' . str_replace('-', ' to ', $field);
        }
        if (str_contains($field, ',')) {
            return 'at ' . str_replace(',', ', ', $field);
        }
        return 'at ' . $field;
    }

    /** @param array<string, mixed> $extra */
    private static function dateConverter(string $action, string $input, array $extra): array
    {
        try {
            if ($action === 'to_unix') {
                $dt = self::parseDate($input, (string) ($extra['tz'] ?? 'UTC'));
                return self::ok((string) $dt->getTimestamp(), ['iso' => $dt->format(DATE_ATOM)]);
            }
            if ($action === 'from_unix') {
                $ts = (int) trim($input);
                $dt = (new \DateTimeImmutable('@' . $ts))->setTimezone(new \DateTimeZone((string) ($extra['tz'] ?? 'UTC')));
                return self::ok($dt->format(DATE_ATOM), ['unix' => (string) $ts]);
            }
            if ($action === 'format') {
                $dt = self::parseDate($input, (string) ($extra['tz'] ?? 'UTC'));
                $fmt = (string) ($extra['format'] ?? 'Y-m-d H:i:s');
                return self::ok($dt->format($fmt));
            }
            return self::err('Unknown action');
        } catch (Throwable $e) {
            return self::err($e->getMessage());
        }
    }

    private static function parseDate(string $input, string $tz): \DateTimeImmutable
    {
        $input = trim($input);
        if (ctype_digit($input)) {
            return (new \DateTimeImmutable('@' . (int) $input))->setTimezone(new \DateTimeZone($tz));
        }
        $dt = \DateTimeImmutable::createFromFormat(DATE_ATOM, $input)
            ?: \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $input)
            ?: new \DateTimeImmutable($input, new \DateTimeZone($tz));
        return $dt->setTimezone(new \DateTimeZone($tz));
    }

    private static function jsonTable(string $action, string $input): array
    {
        try {
            $data = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
            if ($action === 'html') {
                return self::ok(self::jsonToHtmlTable($data));
            }
            if ($action === 'csv') {
                return self::ok(self::jsonToCsv($data));
            }
            return self::err('Unknown action');
        } catch (JsonException $e) {
            return self::err('Invalid JSON: ' . $e->getMessage());
        }
    }

    private static function jsonToHtmlTable(mixed $data): string
    {
        $rows = self::flattenJsonRows($data);
        if ($rows === []) {
            return '<table class="w-full text-sm"><tr><td>Empty</td></tr></table>';
        }
        $headers = array_keys($rows[0]);
        $html = '<table class="w-full text-sm border-collapse"><thead><tr>';
        foreach ($headers as $h) {
            $html .= '<th class="border border-slate-700 px-2 py-1 text-left">' . htmlspecialchars($h) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($headers as $h) {
                $val = $row[$h] ?? '';
                $html .= '<td class="border border-slate-700 px-2 py-1">' . htmlspecialchars((string) $val) . '</td>';
            }
            $html .= '</tr>';
        }
        return $html . '</tbody></table>';
    }

    /** @return list<array<string, scalar|null>> */
    private static function flattenJsonRows(mixed $data): array
    {
        if (!is_array($data)) {
            return [['value' => $data]];
        }
        if (array_is_list($data)) {
            $rows = [];
            foreach ($data as $item) {
                if (is_array($item) && array_is_list($item)) {
                    continue;
                }
                $rows[] = is_array($item) ? self::scalarize($item) : ['value' => $item];
            }
            return $rows !== [] ? $rows : [self::scalarize($data)];
        }
        return [self::scalarize($data)];
    }

    /** @param array<mixed> $item @return array<string, scalar|null> */
    private static function scalarize(array $item): array
    {
        $out = [];
        foreach ($item as $k => $v) {
            $out[(string) $k] = is_scalar($v) || $v === null ? $v : json_encode($v);
        }
        return $out;
    }

    private static function jsonToCsv(mixed $data): string
    {
        $rows = self::flattenJsonRows($data);
        if ($rows === []) {
            return '';
        }
        $headers = array_keys($rows[0]);
        $lines = [implode(',', array_map(self::csvEscape(...), $headers))];
        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(
                fn (string $h) => self::csvEscape((string) ($row[$h] ?? '')),
                $headers
            ));
        }
        return implode("\n", $lines);
    }

    private static function csvEscape(string $value): string
    {
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"' . str_replace('"', '""', $value) . '"';
        }
        return $value;
    }

    private static function jsonYaml(string $action, string $input): array
    {
        try {
            if ($action === 'to_yaml') {
                $data = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
                return self::ok(self::toYaml($data));
            }
            if ($action === 'to_json') {
                $data = self::parseYaml($input);
                return self::ok(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            }
            return self::err('Unknown action');
        } catch (Throwable $e) {
            return self::err($e->getMessage());
        }
    }

    /** @param mixed $data */
    private static function toYaml(mixed $data, int $indent = 0): string
    {
        $pad = str_repeat('  ', $indent);
        if ($data === null) {
            return 'null';
        }
        if (is_bool($data)) {
            return $data ? 'true' : 'false';
        }
        if (is_int($data) || is_float($data)) {
            return (string) $data;
        }
        if (is_string($data)) {
            return self::yamlQuote($data);
        }
        if (is_array($data)) {
            if ($data === []) {
                return '[]';
            }
            if (array_is_list($data)) {
                $lines = [];
                foreach ($data as $item) {
                    $lines[] = $pad . '- ' . self::toYaml($item, $indent + 1);
                }
                return implode("\n", $lines);
            }
            $lines = [];
            foreach ($data as $k => $v) {
                $child = self::toYaml($v, $indent + 1);
                $lines[] = $pad . $k . ': ' . (str_contains($child, "\n") ? "\n" . $child : $child);
            }
            return implode("\n", $lines);
        }
        return (string) $data;
    }

    private static function yamlQuote(string $s): string
    {
        if ($s === '' || preg_match('/[:\-\[\]{}#,&*!|>\'"%@`]/', $s)) {
            return '"' . addcslashes($s, "\"\\\n\r\t") . '"';
        }
        return $s;
    }

    /** ponytail: simple YAML subset — maps, lists, scalars; upgrade path: symfony/yaml */
    private static function parseYaml(string $input): mixed
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($input)) ?: [];
        if ($lines === []) {
            return [];
        }
        if (str_starts_with(trim($lines[0]), '-')) {
            return self::parseYamlList($lines, 0)['value'];
        }
        return self::parseYamlMap($lines, 0)['value'];
    }

    /** @param list<string> $lines @return array{value: mixed, next: int} */
    private static function parseYamlMap(array $lines, int $start, int $baseIndent = -1): array
    {
        $map = [];
        $i = $start;
        while ($i < count($lines)) {
            $line = $lines[$i];
            if (trim($line) === '') {
                $i++;
                continue;
            }
            $indent = strlen($line) - strlen(ltrim($line));
            if ($baseIndent >= 0 && $indent < $baseIndent) {
                break;
            }
            if (!preg_match('/^(\s*)([A-Za-z0-9_.-]+):\s*(.*)$/', $line, $m)) {
                break;
            }
            $key = $m[2];
            $rest = $m[3];
            if ($rest !== '') {
                $map[$key] = self::parseYamlScalar($rest);
                $i++;
                continue;
            }
            $i++;
            if ($i < count($lines) && preg_match('/^\s*-\s/', $lines[$i])) {
                $parsed = self::parseYamlList($lines, $i, $indent + 2);
                $map[$key] = $parsed['value'];
                $i = $parsed['next'];
            } elseif ($i < count($lines) && preg_match('/^\s+[A-Za-z]/', $lines[$i])) {
                $parsed = self::parseYamlMap($lines, $i, $indent + 2);
                $map[$key] = $parsed['value'];
                $i = $parsed['next'];
            } else {
                $map[$key] = null;
            }
        }
        return ['value' => $map, 'next' => $i];
    }

    /** @param list<string> $lines @return array{value: list<mixed>, next: int} */
    private static function parseYamlList(array $lines, int $start, int $baseIndent = 0): array
    {
        $list = [];
        $i = $start;
        while ($i < count($lines)) {
            $line = $lines[$i];
            if (trim($line) === '') {
                $i++;
                continue;
            }
            if (!preg_match('/^(\s*)-\s*(.*)$/', $line, $m)) {
                break;
            }
            $indent = strlen($m[1]);
            if ($i > $start && $indent < $baseIndent) {
                break;
            }
            $rest = $m[2];
            if ($rest !== '') {
                $list[] = self::parseYamlScalar($rest);
                $i++;
                continue;
            }
            $i++;
            if ($i < count($lines) && preg_match('/^\s+[A-Za-z0-9_.-]+:/', $lines[$i])) {
                $parsed = self::parseYamlMap($lines, $i, $indent + 2);
                $list[] = $parsed['value'];
                $i = $parsed['next'];
            } else {
                $list[] = null;
            }
        }
        return ['value' => $list, 'next' => $i];
    }

    private static function parseYamlScalar(string $raw): mixed
    {
        $raw = trim($raw);
        if ($raw === 'null' || $raw === '~') {
            return null;
        }
        if ($raw === 'true') {
            return true;
        }
        if ($raw === 'false') {
            return false;
        }
        if (is_numeric($raw)) {
            return str_contains($raw, '.') ? (float) $raw : (int) $raw;
        }
        if (
            (str_starts_with($raw, '"') && str_ends_with($raw, '"'))
            || (str_starts_with($raw, "'") && str_ends_with($raw, "'"))
        ) {
            return stripcslashes(substr($raw, 1, -1));
        }
        return $raw;
    }

    /** @param array<string, mixed> $extra */
    private static function numberBase(string $action, string $input, array $extra): array
    {
        $from = max(2, min(36, (int) ($extra['from_base'] ?? 10)));
        $to = max(2, min(36, (int) ($extra['to_base'] ?? 2)));
        $input = strtoupper(trim($input));
        if ($input === '') {
            return self::err('Enter a number');
        }
        try {
            if ($action === 'convert') {
                $decimal = self::parseBase($input, $from);
                return self::ok(self::toBase($decimal, $to), ['from' => $from, 'to' => $to]);
            }
            return self::err('Unknown action');
        } catch (Throwable $e) {
            return self::err($e->getMessage());
        }
    }

    private static function parseBase(string $value, int $base): int
    {
        if ($base === 10) {
            if (!preg_match('/^-?\d+$/', $value)) {
                throw new InvalidArgumentException('Invalid decimal');
            }
            return (int) $value;
        }
        $result = 0;
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '-');
        $digits = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        foreach (str_split($value) as $ch) {
            $pos = strpos($digits, $ch);
            if ($pos === false || $pos >= $base) {
                throw new InvalidArgumentException("Invalid digit {$ch} for base {$base}");
            }
            $result = $result * $base + $pos;
        }
        return $negative ? -$result : $result;
    }

    private static function toBase(int $decimal, int $base): string
    {
        if ($decimal === 0) {
            return '0';
        }
        $negative = $decimal < 0;
        $n = abs($decimal);
        $digits = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $out = '';
        while ($n > 0) {
            $out = $digits[$n % $base] . $out;
            $n = intdiv($n, $base);
        }
        return ($negative ? '-' : '') . $out;
    }

    private static function base64Text(string $action, string $input): array
    {
        try {
            if ($action === 'encode') {
                return self::ok(base64_encode($input));
            }
            if ($action === 'decode') {
                $decoded = base64_decode($input, true);
                if ($decoded === false) {
                    return self::err('Invalid Base64');
                }
                return self::ok($decoded);
            }
            return self::err('Unknown action');
        } catch (Throwable $e) {
            return self::err($e->getMessage());
        }
    }

    private static function base64Image(string $action, string $input): array
    {
        if ($action === 'inspect') {
            $data = $input;
            if (preg_match('/^data:image\/[^;]+;base64,(.+)$/i', $input, $m)) {
                $data = $m[1];
            }
            $bin = base64_decode($data, true);
            if ($bin === false) {
                return self::err('Invalid Base64 image data');
            }
            $info = @getimagesizefromstring($bin);
            if ($info === false) {
                return self::ok('Decoded ' . strlen($bin) . ' bytes (not a recognized image)');
            }
            return self::ok(sprintf(
                '%s image, %dx%d, %d bytes',
                $info['mime'] ?? 'unknown',
                $info[0],
                $info[1],
                strlen($bin)
            ), ['mime' => $info['mime'] ?? '', 'width' => $info[0], 'height' => $info[1]]);
        }
        return self::err('Unknown action');
    }

    private static function certificate(string $input): array
    {
        $pem = trim($input);
        if (!str_contains($pem, 'BEGIN CERTIFICATE')) {
            $pem = "-----BEGIN CERTIFICATE-----\n" . chunk_split(preg_replace('/\s+/', '', $pem), 64, "\n") . "-----END CERTIFICATE-----\n";
        }
        $parsed = openssl_x509_parse($pem);
        if ($parsed === false) {
            return self::err('Could not parse certificate');
        }
        $lines = [
            'Subject: ' . ($parsed['name'] ?? ''),
            'Issuer: ' . ($parsed['issuer']['CN'] ?? json_encode($parsed['issuer'] ?? [])),
            'Valid from: ' . date(DATE_ATOM, (int) ($parsed['validFrom_time_t'] ?? 0)),
            'Valid to: ' . date(DATE_ATOM, (int) ($parsed['validTo_time_t'] ?? 0)),
            'Serial: ' . ($parsed['serialNumber'] ?? ''),
        ];
        return self::ok(implode("\n", $lines), ['parsed' => $parsed]);
    }

    private static function gzip(string $action, string $input): array
    {
        try {
            if ($action === 'compress') {
                $compressed = gzencode($input, 9);
                if ($compressed === false) {
                    return self::err('Compression failed');
                }
                return self::ok(base64_encode($compressed));
            }
            if ($action === 'decompress') {
                $bin = base64_decode($input, true);
                if ($bin === false) {
                    return self::err('Invalid Base64');
                }
                $out = gzdecode($bin);
                if ($out === false) {
                    return self::err('Decompression failed');
                }
                return self::ok($out);
            }
            return self::err('Unknown action');
        } catch (Throwable $e) {
            return self::err($e->getMessage());
        }
    }

    private static function htmlCodec(string $action, string $input): array
    {
        if ($action === 'encode') {
            return self::ok(htmlspecialchars($input, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        }
        if ($action === 'decode') {
            return self::ok(html_entity_decode($input, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        return self::err('Unknown action');
    }

    private static function jwt(string $input): array
    {
        $parts = explode('.', trim($input));
        if (count($parts) !== 3) {
            return self::err('JWT must have 3 parts');
        }
        try {
            $header = json_decode(self::base64UrlDecode($parts[0]), true, 512, JSON_THROW_ON_ERROR);
            $payload = json_decode(self::base64UrlDecode($parts[1]), true, 512, JSON_THROW_ON_ERROR);
            $out = "HEADER:\n" . json_encode($header, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                . "\n\nPAYLOAD:\n" . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $meta = ['exp' => $payload['exp'] ?? null];
            if (isset($payload['exp'])) {
                $meta['expired'] = time() > (int) $payload['exp'];
            }
            return self::ok($out, $meta);
        } catch (Throwable $e) {
            return self::err('Invalid JWT: ' . $e->getMessage());
        }
    }

    private static function base64UrlDecode(string $data): string
    {
        $pad = 4 - (strlen($data) % 4);
        if ($pad < 4) {
            $data .= str_repeat('=', $pad);
        }
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        if ($decoded === false) {
            throw new InvalidArgumentException('Invalid base64url segment');
        }
        return $decoded;
    }

    private static function urlCodec(string $action, string $input): array
    {
        if ($action === 'encode') {
            return self::ok(rawurlencode($input));
        }
        if ($action === 'decode') {
            return self::ok(rawurldecode($input));
        }
        return self::err('Unknown action');
    }

    private static function jsonFormat(string $action, string $input): array
    {
        try {
            $data = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
            if ($action === 'format') {
                return self::ok(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            }
            if ($action === 'minify') {
                return self::ok(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            }
            if ($action === 'validate') {
                return self::ok('Valid JSON');
            }
            return self::err('Unknown action');
        } catch (JsonException $e) {
            return self::err($e->getMessage());
        }
    }

    private static function sqlFormat(string $action, string $input): array
    {
        $sql = trim($input);
        if ($sql === '') {
            return self::err('Enter SQL');
        }
        if ($action === 'format') {
            return self::ok(self::formatSql($sql));
        }
        if ($action === 'minify') {
            return self::ok(preg_replace('/\s+/', ' ', $sql) ?? $sql);
        }
        return self::err('Unknown action');
    }

    private static function formatSql(string $sql): string
    {
        $keywords = [
            'SELECT', 'FROM', 'WHERE', 'JOIN', 'LEFT', 'RIGHT', 'INNER', 'OUTER', 'ON', 'AND', 'OR',
            'GROUP BY', 'ORDER BY', 'HAVING', 'LIMIT', 'INSERT', 'INTO', 'VALUES', 'UPDATE', 'SET', 'DELETE',
        ];
        $normalized = $sql;
        foreach ($keywords as $kw) {
            $normalized = preg_replace('/\b' . preg_quote($kw, '/') . '\b/i', "\n" . strtoupper($kw), $normalized) ?? $normalized;
        }
        $lines = array_values(array_filter(array_map('trim', explode("\n", $normalized))));
        return implode("\n", $lines);
    }

    private static function xmlFormat(string $action, string $input): array
    {
        if (trim($input) === '') {
            return self::err('Enter XML');
        }
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        if (@$dom->loadXML($input) === false) {
            return self::err('Invalid XML');
        }
        if ($action === 'format') {
            return self::ok($dom->saveXML() ?: '');
        }
        if ($action === 'minify') {
            return self::ok(preg_replace('/>\s+</', '><', $dom->saveXML() ?: '') ?? '');
        }
        if ($action === 'validate') {
            return self::ok('Valid XML');
        }
        return self::err('Unknown action');
    }

    /** @param array<string, mixed> $extra */
    private static function hashGen(string $input, array $extra): array
    {
        $algo = (string) ($extra['algorithm'] ?? 'sha256');
        $allowed = hash_algos();
        if (!in_array($algo, $allowed, true)) {
            return self::err('Unsupported algorithm');
        }
        $lines = [$algo . ': ' . hash($algo, $input)];
        foreach (['md5', 'sha1', 'sha256', 'sha512'] as $a) {
            if ($a !== $algo && in_array($a, $allowed, true)) {
                $lines[] = $a . ': ' . hash($a, $input);
            }
        }
        return self::ok(implode("\n", $lines));
    }

    /** @param array<string, mixed> $extra */
    private static function loremIpsum(array $extra): array
    {
        $paragraphs = max(1, min(20, (int) ($extra['paragraphs'] ?? 3)));
        $words = [
            'lorem', 'ipsum', 'dolor', 'sit', 'amet', 'consectetur', 'adipiscing', 'elit',
            'sed', 'do', 'eiusmod', 'tempor', 'incididunt', 'ut', 'labore', 'dolore',
        ];
        $out = [];
        for ($p = 0; $p < $paragraphs; $p++) {
            $count = random_int(40, 80);
            $sentence = [];
            for ($w = 0; $w < $count; $w++) {
                $sentence[] = $words[array_rand($words)];
            }
            $sentence[0] = ucfirst($sentence[0]);
            $out[] = implode(' ', $sentence) . '.';
        }
        return self::ok(implode("\n\n", $out));
    }

    /** @param array<string, mixed> $extra */
    private static function password(array $extra): array
    {
        $length = max(8, min(128, (int) ($extra['length'] ?? 16)));
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        if (($extra['symbols'] ?? true)) {
            $chars .= '!@#$%^&*()-_=+';
        }
        $max = strlen($chars) - 1;
        $pass = '';
        for ($i = 0; $i < $length; $i++) {
            $pass .= $chars[random_int(0, $max)];
        }
        return self::ok($pass, ['length' => $length]);
    }

    /** @param array<string, mixed> $extra */
    private static function uuid(array $extra): array
    {
        $uuids = [];
        for ($i = 0; $i < self::UUID_BATCH; $i++) {
            $uuids[] = self::uuid4();
        }
        return self::ok(implode("\n", $uuids));
    }

    private static function uuid4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

    private static function jsonPath(string $input, string $path): array
    {
        try {
            $data = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
            $matches = self::evaluateJsonPath($data, $path);
            return self::ok(json_encode($matches, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), [
                'count' => count($matches),
            ]);
        } catch (Throwable $e) {
            return self::err($e->getMessage());
        }
    }

    /** ponytail: basic JSONPath ($, $.a, $..key); upgrade path: full JSONPath lib */
    private static function evaluateJsonPath(mixed $data, string $path): array
    {
        $path = trim($path);
        if ($path === '' || $path === '$') {
            return [$data];
        }
        if (str_starts_with($path, '$..')) {
            $key = substr($path, 3);
            return self::findDeep($data, $key);
        }
        if (str_starts_with($path, '$.')) {
            $segments = preg_split('/\.(?![^\[]*\])/', substr($path, 2)) ?: [];
            $cur = $data;
            foreach ($segments as $seg) {
                if ($seg === '') {
                    continue;
                }
                if (preg_match('/^(.+)\[(\d+)\]$/', $seg, $m)) {
                    $key = $m[1];
                    $idx = (int) $m[2];
                    if (!is_array($cur) || !array_key_exists($key, $cur)) {
                        return [];
                    }
                    $cur = $cur[$key];
                    if (!is_array($cur) || !array_key_exists($idx, $cur)) {
                        return [];
                    }
                    $cur = $cur[$idx];
                    continue;
                }
                if (!is_array($cur) || !array_key_exists($seg, $cur)) {
                    return [];
                }
                $cur = $cur[$seg];
            }
            return [$cur];
        }
        throw new InvalidArgumentException('Unsupported JSONPath — use $, $.field, or $..field');
    }

    /** @return list<mixed> */
    private static function findDeep(mixed $data, string $key): array
    {
        $found = [];
        if (is_array($data)) {
            if (array_key_exists($key, $data)) {
                $found[] = $data[$key];
            }
            foreach ($data as $v) {
                $found = array_merge($found, self::findDeep($v, $key));
            }
        }
        return $found;
    }

    /** @param array<string, mixed> $extra */
    private static function regexTest(string $input, string $pattern, array $extra): array
    {
        if ($pattern === '') {
            return self::err('Enter a pattern');
        }
        $flags = (string) ($extra['flags'] ?? '');
        $delim = '/';
        $full = $delim . str_replace($delim, '\\' . $delim, $pattern) . $delim . $flags;
        set_error_handler(static fn () => true);
        $ok = @preg_match_all($full, $input, $matches, PREG_OFFSET_CAPTURE);
        restore_error_handler();
        if ($ok === false) {
            return self::err('Invalid regular expression');
        }
        $lines = ['Matches: ' . $ok];
        foreach ($matches[0] as $i => $m) {
            $lines[] = sprintf('#%d offset %d: %s', $i + 1, $m[1], $m[0]);
        }
        if (isset($matches[1]) && $matches[1] !== []) {
            $lines[] = 'Groups: ' . json_encode(array_column($matches, 1));
        }
        return self::ok(implode("\n", $lines), ['count' => $ok]);
    }

    private static function xmlTester(string $action, string $input, string $xpath): array
    {
        if (trim($input) === '') {
            return self::err('Enter XML');
        }
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        if (@$dom->loadXML($input) === false) {
            $errs = array_map(fn ($e) => trim($e->message), libxml_get_errors());
            libxml_clear_errors();
            return self::err(implode('; ', $errs) ?: 'Invalid XML');
        }
        if ($action === 'validate') {
            return self::ok('Valid XML');
        }
        if ($action === 'xpath' && $xpath !== '') {
            $result = (new \DOMXPath($dom))->query($xpath);
            if ($result === false) {
                return self::err('Invalid XPath');
            }
            $nodes = [];
            foreach ($result as $node) {
                $nodes[] = $dom->saveXML($node) ?: $node->textContent;
            }
            return self::ok(implode("\n---\n", $nodes), ['count' => count($nodes)]);
        }
        return self::err('Unknown action');
    }

    private static function escapeUnescape(string $action, string $input, string $mode): array
    {
        if ($action === 'escape') {
            $out = match ($mode) {
                'json' => json_encode($input, JSON_UNESCAPED_UNICODE) ?: '""',
                'html' => htmlspecialchars($input, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                'url' => rawurlencode($input),
                'sql' => addslashes($input),
                default => null,
            };
            return $out === null ? self::err('Unknown mode') : self::ok($out);
        }
        if ($action === 'unescape') {
            $out = match ($mode) {
                'json' => is_string(json_decode('"' . addcslashes($input, "\"\\") . '"')) ? json_decode('"' . addcslashes($input, "\"\\") . '"') : $input,
                'html' => html_entity_decode($input, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'url' => rawurldecode($input),
                'sql' => stripcslashes($input),
                default => $input,
            };
            return self::ok((string) $out);
        }
        return self::err('Unknown action');
    }

    private static function listCompare(string $a, string $b): array
    {
        $left = self::lines($a);
        $right = self::lines($b);
        $onlyLeft = array_values(array_diff($left, $right));
        $onlyRight = array_values(array_diff($right, $left));
        $both = array_values(array_intersect($left, $right));
        $out = "In both (" . count($both) . "):\n" . implode("\n", $both)
            . "\n\nOnly left (" . count($onlyLeft) . "):\n" . implode("\n", $onlyLeft)
            . "\n\nOnly right (" . count($onlyRight) . "):\n" . implode("\n", $onlyRight);
        return self::ok($out, ['both' => count($both), 'left_only' => count($onlyLeft), 'right_only' => count($onlyRight)]);
    }

    /** @return list<string> */
    private static function lines(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $text) ?: []), fn ($l) => $l !== ''));
    }

    private static function markdownPreview(string $input): array
    {
        return self::ok(self::markdownToHtml($input));
    }

    /** ponytail: minimal markdown — headings, bold, italic, code, links */
    private static function markdownToHtml(string $md): string
    {
        $html = htmlspecialchars($md, ENT_QUOTES, 'UTF-8');
        $html = preg_replace('/^### (.+)$/m', '<h3 class="text-lg font-semibold mt-4">$1</h3>', $html) ?? $html;
        $html = preg_replace('/^## (.+)$/m', '<h2 class="text-xl font-semibold mt-4">$1</h2>', $html) ?? $html;
        $html = preg_replace('/^# (.+)$/m', '<h1 class="text-2xl font-bold mt-4">$1</h1>', $html) ?? $html;
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html) ?? $html;
        $html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html) ?? $html;
        $html = preg_replace('/`(.+?)`/', '<code class="bg-slate-800 px-1 rounded">$1</code>', $html) ?? $html;
        $html = preg_replace('/\[(.+?)\]\((.+?)\)/', '<a href="$2" class="text-indigo-400 underline">$1</a>', $html) ?? $html;
        $html = nl2br($html);
        return '<div class="prose prose-invert max-w-none">' . $html . '</div>';
    }

    private static function textAnalyzer(string $input, string $action): array
    {
        if ($action === 'upper') {
            return self::ok(strtoupper($input));
        }
        if ($action === 'lower') {
            return self::ok(strtolower($input));
        }
        if ($action === 'title') {
            return self::ok(ucwords(strtolower($input)));
        }
        if ($action === 'trim') {
            return self::ok(trim($input));
        }
        $chars = strlen($input);
        $words = str_word_count($input);
        $lines = substr_count($input, "\n") + ($input === '' ? 0 : 1);
        return self::ok("Characters: {$chars}\nWords: {$words}\nLines: {$lines}", compact('chars', 'words', 'lines'));
    }

    private static function textCompare(string $a, string $b): array
    {
        $left = explode("\n", $a);
        $right = explode("\n", $b);
        $max = max(count($left), count($right));
        $lines = [];
        for ($i = 0; $i < $max; $i++) {
            $l = $left[$i] ?? '';
            $r = $right[$i] ?? '';
            if ($l === $r) {
                $lines[] = '  ' . $l;
            } elseif ($l === '') {
                $lines[] = '+ ' . $r;
            } elseif ($r === '') {
                $lines[] = '- ' . $l;
            } else {
                $lines[] = '- ' . $l;
                $lines[] = '+ ' . $r;
            }
        }
        return self::ok(implode("\n", $lines));
    }
}
