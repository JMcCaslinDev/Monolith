<?php

declare(strict_types=1);

namespace Tests\Unit;

use CursorShare\Catalog;
use CursorShare\ContentGuard;
use PHPUnit\Framework\TestCase;

/** Category and content validation for community Cursor asset posts. */
final class ContentGuardTest extends TestCase
{
    /** Rejects script tags so uploaded rules cannot inject browser code. */
    public function test_rejects_script_injection(): void
    {
        $this->assertTrue(ContentGuard::containsInjection('<script>alert(1)</script>'));
        $result = ContentGuard::validatePost(
            'rules',
            'Safe title',
            '',
            'rule.mdc',
            "---\ndescription: x\n---\n<script>evil()</script>",
        );
        $this->assertFalse($result['ok']);
    }

    /** Hooks must be valid JSON before they are stored or served for download. */
    public function test_hooks_require_valid_json(): void
    {
        $bad = ContentGuard::validatePost('hooks', 'Hooks', '', 'hooks.json', '{not json');
        $this->assertFalse($bad['ok']);

        $good = ContentGuard::validatePost('hooks', 'Hooks', '', 'hooks.json', '{"version":1}');
        $this->assertTrue($good['ok']);
    }

    /** Profanity filter blocks posts that would appear in public browse lists. */
    public function test_blocks_bad_words_in_title(): void
    {
        $result = ContentGuard::validatePost('commands', 'what the fuck', '', 'cmd.md', '# hi');
        $this->assertFalse($result['ok']);
    }

    /** Skills normalize to SKILL.md so downloads match Cursor layout expectations. */
    public function test_skills_normalize_filename(): void
    {
        $filename = Catalog::normalizeFilename('skills', 'my-skill');
        $this->assertSame('SKILL.md', $filename);
    }
}
