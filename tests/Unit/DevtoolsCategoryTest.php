<?php

declare(strict_types=1);

namespace Tests\Unit;

use Devtools\Catalog;
use Devtools\Toolbox;
use PHPUnit\Framework\TestCase;

/** Per-category Dev Tools behavior: success paths, errors, and client-only tools. */
final class DevtoolsCategoryTest extends TestCase
{
    private const CLIENT_ONLY = ['qr-code', 'color-blindness', 'image-converter'];

    // --- Converters ---

    /** Converters: number base converts decimal to binary for quick radix checks. */
    public function test_converters_number_base_success(): void
    {
        $result = Toolbox::process('number-base', 'convert', [
            'input' => '10',
            'extra' => ['from_base' => 10, 'to_base' => 2],
        ]);
        $this->assertSame('1010', $result['output']);
    }

    /** Converters: cron parser rejects empty input so users get feedback instead of silence. */
    public function test_converters_cron_empty_input_error(): void
    {
        $result = Toolbox::process('cron-parser', 'describe', ['input' => '']);
        $this->assertArrayHasKey('error', $result);
    }

    /** Converters: cron parser rejects malformed field counts with a clear error. */
    public function test_converters_cron_invalid_fields_error(): void
    {
        $result = Toolbox::process('cron-parser', 'describe', ['input' => 'only three']);
        $this->assertArrayHasKey('error', $result);
    }

    /** Converters: JSON table requires valid JSON before rendering HTML. */
    public function test_converters_json_table_invalid_json_error(): void
    {
        $result = Toolbox::process('json-table', 'html', ['input' => '{bad']);
        $this->assertArrayHasKey('error', $result);
    }

    /** Converters: date tool reads Unix epoch into a human-readable UTC string. */
    public function test_converters_date_from_unix_success(): void
    {
        $result = Toolbox::process('date', 'from_unix', ['input' => '0', 'extra' => ['tz' => 'UTC']]);
        $this->assertStringContainsString('1970', $result['output']);
    }

    // --- Encoders ---

    /** Encoders: Base64 round-trips text without corruption. */
    public function test_encoders_base64_round_trip(): void
    {
        $encoded = Toolbox::process('base64-text', 'encode', ['input' => 'hello']);
        $decoded = Toolbox::process('base64-text', 'decode', ['input' => $encoded['output']]);
        $this->assertSame('hello', $decoded['output']);
    }

    /** Encoders: JWT decoder surfaces the payload subject for token inspection. */
    public function test_encoders_jwt_decodes_subject(): void
    {
        $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.'
            . 'eyJzdWIiOiIxMjM0NTY3ODkwIn0.'
            . 'signature';
        $result = Toolbox::process('jwt', 'run', ['input' => $token]);
        $this->assertStringContainsString('1234567890', $result['output']);
    }

    /** Encoders: certificate parser rejects garbage PEM instead of fake metadata. */
    public function test_encoders_certificate_invalid_pem_error(): void
    {
        $result = Toolbox::process('certificate', 'run', ['input' => 'not-a-certificate']);
        $this->assertArrayHasKey('error', $result);
    }

    /** Encoders: URL codec handles spaces and reserved characters. */
    public function test_encoders_url_encode_decode(): void
    {
        $encoded = Toolbox::process('url', 'encode', ['input' => 'a b&c']);
        $decoded = Toolbox::process('url', 'decode', ['input' => $encoded['output']]);
        $this->assertSame('a b&c', $decoded['output']);
    }

    // --- Formatters ---

    /** Formatters: JSON pretty-print adds line breaks for readability. */
    public function test_formatters_json_pretty_print(): void
    {
        $result = Toolbox::process('json', 'format', ['input' => '{"a":1}']);
        $this->assertStringContainsString("\n", $result['output']);
    }

    /** Formatters: JSON validator reports syntax errors on malformed input. */
    public function test_formatters_json_validate_error(): void
    {
        $result = Toolbox::process('json', 'validate', ['input' => '{bad']);
        $this->assertArrayHasKey('error', $result);
    }

    /** Formatters: XML validator accepts well-formed documents. */
    public function test_formatters_xml_validate_success(): void
    {
        $result = Toolbox::process('xml', 'validate', ['input' => '<root><item/></root>']);
        $this->assertSame('Valid XML', $result['output']);
    }

    // --- Generators ---

    /** Generators: UUID tool emits five RFC-4122 v4 identifiers per run. */
    public function test_generators_uuid_format(): void
    {
        $result = Toolbox::process('uuid', 'run', []);
        $lines = array_values(array_filter(explode("\n", trim($result['output']))));
        $this->assertCount(5, $lines);
    }

    /** Generators: password length option is honored for generated secrets. */
    public function test_generators_password_length(): void
    {
        $result = Toolbox::process('password', 'run', ['extra' => ['length' => 20, 'symbols' => false]]);
        $this->assertSame(20, strlen($result['output']));
    }

    /** Generators: hash output includes algorithm label for identification. */
    public function test_generators_hash_labels_algorithm(): void
    {
        $result = Toolbox::process('hash', 'run', ['input' => 'x', 'extra' => ['algorithm' => 'sha256']]);
        $this->assertStringContainsString('sha256:', $result['output']);
    }

    // --- Graphic (client-only) ---

    /** Graphic: QR, color-blindness, and image-converter run in the browser only. */
    public function test_graphic_tools_are_client_only(): void
    {
        foreach (Catalog::categories()['graphic']['tools'] as $tool) {
            $this->assertContains($tool['slug'], self::CLIENT_ONLY);
        }
    }

    /** Graphic: category permission grants access to both graphic tools. */
    public function test_graphic_category_permission_covers_tools(): void
    {
        $perms = ['devtools.graphic.use'];
        $this->assertTrue(Catalog::canUse('color-blindness', $perms));
        $this->assertTrue(Catalog::canUse('image-converter', $perms));
    }

    /** Graphic: color-blindness self-check script validates simulation matrices. */
    public function test_graphic_color_blindness_script_passes(): void
    {
        $script = dirname(__DIR__, 2) . '/scripts/check-devtools-color-blindness.mjs';
        exec('node ' . escapeshellarg($script), $output, $code);
        $this->assertSame(0, $code, implode("\n", $output));
    }

    // --- Testers ---

    /** Testers: JSONPath finds nested values in complex documents. */
    public function test_testers_jsonpath_finds_value(): void
    {
        $result = Toolbox::process('jsonpath', 'run', [
            'input' => '{"a":{"b":"found"}}',
            'extra' => ['path' => '$.a.b'],
        ]);
        $this->assertStringContainsString('found', $result['output']);
    }

    /** Testers: regex tool counts matches in sample text. */
    public function test_testers_regex_counts_matches(): void
    {
        $result = Toolbox::process('regex', 'run', [
            'input' => 'foo bar foo',
            'extra' => ['pattern' => 'foo', 'flags' => ''],
        ]);
        $this->assertStringContainsString('Matches: 2', $result['output']);
    }

    /** Testers: XML tester XPath query returns matching node content. */
    public function test_testers_xml_xpath_success(): void
    {
        $result = Toolbox::process('xml-tester', 'xpath', [
            'input' => '<root><item>hi</item></root>',
            'extra' => ['xpath' => '//item'],
        ]);
        $this->assertStringContainsString('hi', $result['output']);
    }

    // --- Text ---

    /** Text: list compare shows lines unique to each side. */
    public function test_text_list_compare_differences(): void
    {
        $result = Toolbox::process('list-compare', 'run', [
            'input' => "a\nb",
            'input_b' => "a\nc",
        ]);
        $this->assertStringContainsString('Only left', $result['output']);
        $this->assertStringContainsString('b', $result['output']);
    }

    /** Text: text compare marks added and removed lines in a diff. */
    public function test_text_compare_diff_markers(): void
    {
        $result = Toolbox::process('text-compare', 'run', [
            'input' => "same\nleft",
            'input_b' => "same\nright",
        ]);
        $this->assertStringContainsString('- left', $result['output']);
        $this->assertStringContainsString('+ right', $result['output']);
    }

    /** Text: markdown preview renders headings to HTML. */
    public function test_text_markdown_preview_heading(): void
    {
        $result = Toolbox::process('markdown-preview', 'run', ['input' => '# Hi']);
        $this->assertStringContainsString('<h1', $result['output']);
    }
}
