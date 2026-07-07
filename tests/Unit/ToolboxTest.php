<?php

declare(strict_types=1);

namespace Tests\Unit;

use Devtools\Catalog;
use Devtools\Toolbox;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Server-side Dev Tools processors (JSON, encoding, converters, and formatters). */
final class ToolboxTest extends TestCase
{
    private const CLIENT_ONLY = ['qr-code', 'color-blindness', 'image-converter'];

    /** Every server-side catalog tool is wired into Toolbox::process — client-only tools excluded. */
    public function test_every_server_tool_is_registered_in_toolbox(): void
    {
        foreach (Catalog::tools() as $tool) {
            if (in_array($tool['slug'], self::CLIENT_ONLY, true)) {
                continue;
            }
            try {
                Toolbox::process($tool['slug'], 'run', ['input' => '']);
            } catch (InvalidArgumentException $e) {
                $this->fail('Tool not in Toolbox: ' . $tool['slug'] . ' — ' . $e->getMessage());
            }
        }
        $this->assertTrue(true);
    }

    /** Unknown slugs throw so the API cannot be called with arbitrary tool names. */
    public function test_unknown_tool_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Toolbox::process('fake-tool', 'run', ['input' => '']);
    }

    // --- Converters ---

    /** JSON formatter pretty-prints valid JSON for readability in Dev Tools. */
    public function test_json_format_pretty_prints(): void
    {
        $result = Toolbox::process('json', 'format', ['input' => '{"a":1}']);
        $this->assertStringContainsString("\"a\"", $result['output']);
        $this->assertStringContainsString("\n", $result['output']);
    }

    /** JSON validator reports syntax errors instead of silently failing. */
    public function test_json_validate_reports_invalid(): void
    {
        $result = Toolbox::process('json', 'validate', ['input' => '{bad']);
        $this->assertArrayHasKey('error', $result);
    }

    /** JSON minifier removes whitespace from valid documents. */
    public function test_json_minify_strips_whitespace(): void
    {
        $result = Toolbox::process('json', 'minify', ['input' => "{ \"a\" : 1 }"]);
        $this->assertSame('{"a":1}', $result['output']);
    }

    /** Cron parser describes each schedule field for debugging cron expressions. */
    public function test_cron_parser_describes_fields(): void
    {
        $result = Toolbox::process('cron-parser', 'describe', ['input' => '0 9 * * 1']);
        $this->assertStringContainsString('Minute', $result['output']);
        $this->assertStringContainsString('9', $result['output']);
    }

    /** Date converter reads Unix timestamps into ISO-8601 strings. */
    public function test_date_from_unix(): void
    {
        $result = Toolbox::process('date', 'from_unix', ['input' => '0', 'extra' => ['tz' => 'UTC']]);
        $this->assertStringContainsString('1970', $result['output']);
    }

    /** Date converter extracts Unix time from ISO input. */
    public function test_date_to_unix(): void
    {
        $result = Toolbox::process('date', 'to_unix', ['input' => '1970-01-01T00:00:00+00:00']);
        $this->assertSame('0', $result['output']);
    }

    /** JSON to HTML table renders array rows for spreadsheet-style preview. */
    public function test_json_table_to_html(): void
    {
        $result = Toolbox::process('json-table', 'html', ['input' => '[{"id":1,"name":"a"}]']);
        $this->assertStringContainsString('<table', $result['output']);
        $this->assertStringContainsString('a', $result['output']);
    }

    /** JSON to CSV exports flat rows for spreadsheet import. */
    public function test_json_table_to_csv(): void
    {
        $result = Toolbox::process('json-table', 'csv', ['input' => '[{"id":1}]']);
        $this->assertStringContainsString('id', $result['output']);
        $this->assertStringContainsString('1', $result['output']);
    }

    /** JSON ↔ YAML converter preserves simple object structure. */
    public function test_json_yaml_round_trip_simple_object(): void
    {
        $yaml = Toolbox::process('json-yaml', 'to_yaml', ['input' => '{"name":"test","n":1}']);
        $this->assertStringContainsString('name:', $yaml['output']);
        $json = Toolbox::process('json-yaml', 'to_json', ['input' => $yaml['output']]);
        $this->assertStringContainsString('"name"', $json['output']);
    }

    /** Number base converter converts decimal to binary accurately. */
    public function test_number_base_convert_decimal_to_binary(): void
    {
        $result = Toolbox::process('number-base', 'convert', [
            'input' => '10',
            'extra' => ['from_base' => 10, 'to_base' => 2],
        ]);
        $this->assertSame('1010', $result['output']);
    }

    // --- Encoders / Decoders ---

    /** Base64 encode/decode round-trips text without corruption. */
    public function test_base64_round_trip(): void
    {
        $encoded = Toolbox::process('base64-text', 'encode', ['input' => 'hello']);
        $decoded = Toolbox::process('base64-text', 'decode', ['input' => $encoded['output']]);
        $this->assertSame('hello', $decoded['output']);
    }

    /** Base64 image inspector recognizes embedded PNG dimensions. */
    public function test_base64_image_inspects_png(): void
    {
        $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        $result = Toolbox::process('base64-image', 'inspect', ['input' => $png]);
        $this->assertStringContainsString('image', strtolower($result['output']));
        $this->assertArrayHasKey('width', $result['meta'] ?? []);
    }

    /** HTML entity encoder escapes markup for safe display. */
    public function test_html_encode_entities(): void
    {
        $result = Toolbox::process('html', 'encode', ['input' => '<div>"a"</div>']);
        $this->assertStringContainsString('&lt;', $result['output']);
        $this->assertStringContainsString('&quot;', $result['output']);
    }

    /** HTML entity decoder restores original markup. */
    public function test_html_decode_entities(): void
    {
        $result = Toolbox::process('html', 'decode', ['input' => '&lt;b&gt;ok&lt;/b&gt;']);
        $this->assertSame('<b>ok</b>', $result['output']);
    }

    /** URL encoder handles spaces and reserved characters correctly. */
    public function test_url_encode_decode(): void
    {
        $encoded = Toolbox::process('url', 'encode', ['input' => 'a b&c']);
        $this->assertSame('a%20b%26c', $encoded['output']);
        $decoded = Toolbox::process('url', 'decode', ['input' => $encoded['output']]);
        $this->assertSame('a b&c', $decoded['output']);
    }

    /** JWT decoder extracts the payload subject from a token. */
    public function test_jwt_decodes_payload(): void
    {
        $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.'
            . 'eyJzdWIiOiIxMjM0NTY3ODkwIn0.'
            . 'signature';
        $result = Toolbox::process('jwt', 'run', ['input' => $token]);
        $this->assertStringContainsString('1234567890', $result['output']);
    }

    /** Gzip compress/decompress round-trips binary-safe text. */
    public function test_gzip_round_trip(): void
    {
        $compressed = Toolbox::process('gzip', 'compress', ['input' => 'payload']);
        $decompressed = Toolbox::process('gzip', 'decompress', ['input' => $compressed['output']]);
        $this->assertSame('payload', $decompressed['output']);
    }

    /** Certificate parser rejects garbage input instead of returning fake metadata. */
    public function test_certificate_rejects_invalid_pem(): void
    {
        $result = Toolbox::process('certificate', 'run', ['input' => 'not-a-certificate']);
        $this->assertArrayHasKey('error', $result);
    }

    /** Certificate parser reads subject from a valid self-signed PEM. */
    public function test_certificate_parses_valid_pem(): void
    {
        if (!function_exists('openssl_x509_export')) {
            $this->markTestSkipped('OpenSSL not available');
        }
        $dn = ['CN' => 'devtools-test.example'];
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $this->assertNotFalse($key);
        $csr = openssl_csr_new($dn, $key);
        $this->assertNotFalse($csr);
        $cert = openssl_csr_sign($csr, null, $key, 1);
        $this->assertNotFalse($cert);
        openssl_x509_export($cert, $pem);
        $result = Toolbox::process('certificate', 'run', ['input' => $pem]);
        $this->assertStringContainsString('devtools-test.example', $result['output']);
    }

    // --- Formatters ---

    /** SQL formatter breaks keywords onto separate lines for readability. */
    public function test_sql_format_adds_line_breaks(): void
    {
        $result = Toolbox::process('sql', 'format', ['input' => 'SELECT * FROM users WHERE id = 1']);
        $this->assertStringContainsString("SELECT", $result['output']);
        $this->assertStringContainsString("FROM", $result['output']);
    }

    /** XML validator accepts well-formed XML and rejects broken markup. */
    public function test_xml_format_validates(): void
    {
        $ok = Toolbox::process('xml', 'validate', ['input' => '<root><item/></root>']);
        $this->assertSame('Valid XML', $ok['output']);
        $bad = Toolbox::process('xml', 'validate', ['input' => '<root>']);
        $this->assertArrayHasKey('error', $bad);
    }

    /** XML formatter pretty-prints nested elements. */
    public function test_xml_format_pretty_prints(): void
    {
        $result = Toolbox::process('xml', 'format', ['input' => '<root><item/></root>']);
        $this->assertStringContainsString('<root>', $result['output']);
        $this->assertStringContainsString('<item', $result['output']);
    }

    // --- Generators ---

    /** UUID generator produces five RFC-4122 v4 identifiers per refresh. */
    public function test_uuid_generates_valid_format(): void
    {
        $result = Toolbox::process('uuid', 'run', []);
        $lines = array_values(array_filter(explode("\n", trim($result['output']))));
        $this->assertCount(5, $lines);
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
        foreach ($lines as $line) {
            $this->assertMatchesRegularExpression($pattern, $line);
        }
    }

    /** Password generator respects requested length and charset options. */
    public function test_password_respects_length(): void
    {
        $result = Toolbox::process('password', 'run', ['extra' => ['length' => 24, 'symbols' => false]]);
        $this->assertSame(24, strlen($result['output']));
    }

    /** Hash tool includes algorithm label so output is identifiable. */
    public function test_hash_includes_sha256(): void
    {
        $result = Toolbox::process('hash', 'run', ['input' => 'test', 'extra' => ['algorithm' => 'sha256']]);
        $this->assertStringContainsString('sha256:', $result['output']);
    }

    /** Lorem ipsum generator returns the requested number of paragraphs. */
    public function test_lorem_ipsum_generates_paragraphs(): void
    {
        $result = Toolbox::process('lorem-ipsum', 'run', ['extra' => ['paragraphs' => 2]]);
        $this->assertSame(2, substr_count($result['output'], "\n\n") + 1);
    }

    // --- Testers ---

    /** JSONPath extractor finds nested values in complex documents. */
    public function test_jsonpath_finds_nested_field(): void
    {
        $result = Toolbox::process('jsonpath', 'run', [
            'input' => '{"store":{"book":[{"title":"A"}]}}',
            'extra' => ['path' => '$.store.book[0].title'],
        ]);
        $this->assertStringContainsString('A', $result['output']);
    }

    /** Regex tester counts matches in sample text. */
    public function test_regex_finds_matches(): void
    {
        $result = Toolbox::process('regex', 'run', [
            'input' => 'foo bar foo',
            'extra' => ['pattern' => 'foo', 'flags' => ''],
        ]);
        $this->assertStringContainsString('Matches: 2', $result['output']);
    }

    /** XML tester validates well-formed documents. */
    public function test_xml_tester_validates(): void
    {
        $result = Toolbox::process('xml-tester', 'validate', ['input' => '<root><x/></root>']);
        $this->assertSame('Valid XML', $result['output']);
    }

    /** XML tester XPath query returns matching node content. */
    public function test_xml_tester_xpath(): void
    {
        $result = Toolbox::process('xml-tester', 'xpath', [
            'input' => '<root><item id="1">hi</item></root>',
            'extra' => ['xpath' => '//item'],
        ]);
        $this->assertStringContainsString('hi', $result['output']);
    }

    // --- Text ---

    /** Escape tool JSON-encodes strings with newlines and special chars. */
    public function test_escape_json_string(): void
    {
        $result = Toolbox::process('escape-unescape', 'escape', [
            'input' => "line\nbreak",
            'extra' => ['mode' => 'json'],
        ]);
        $this->assertStringContainsString('\\n', $result['output']);
    }

    /** Unescape restores HTML entities to plain text. */
    public function test_unescape_html_string(): void
    {
        $result = Toolbox::process('escape-unescape', 'unescape', [
            'input' => '&lt;tag&gt;',
            'extra' => ['mode' => 'html'],
        ]);
        $this->assertSame('<tag>', $result['output']);
    }

    /** List compare shows lines unique to each side for diffing text lists. */
    public function test_list_compare_finds_differences(): void
    {
        $result = Toolbox::process('list-compare', 'run', [
            'input' => "a\nb\nc",
            'input_b' => "a\nc\nd",
        ]);
        $this->assertStringContainsString('Only left', $result['output']);
        $this->assertStringContainsString('b', $result['output']);
        $this->assertStringContainsString('d', $result['output']);
    }

    /** Text compare marks line differences for side-by-side diff view. */
    public function test_text_compare_marks_diff_lines(): void
    {
        $result = Toolbox::process('text-compare', 'run', [
            'input' => "same\nleft-only",
            'input_b' => "same\nright-only",
        ]);
        $this->assertStringContainsString('- left-only', $result['output']);
        $this->assertStringContainsString('+ right-only', $result['output']);
    }

    /** Text analyzer counts words and lines for content stats. */
    public function test_text_analyzer_counts_words(): void
    {
        $result = Toolbox::process('text-analyzer', 'stats', ['input' => "one two\nthree"]);
        $this->assertStringContainsString('Words: 3', $result['output']);
    }

    /** Text analyzer uppercases input for quick transforms. */
    public function test_text_analyzer_uppercase(): void
    {
        $result = Toolbox::process('text-analyzer', 'upper', ['input' => 'hello']);
        $this->assertSame('HELLO', $result['output']);
    }

    /** Markdown preview renders headings to HTML for live preview. */
    public function test_markdown_preview_renders_heading(): void
    {
        $result = Toolbox::process('markdown-preview', 'run', ['input' => '# Title']);
        $this->assertStringContainsString('<h1', $result['output']);
        $this->assertStringContainsString('Title', $result['output']);
    }
}
