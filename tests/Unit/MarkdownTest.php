<?php

declare(strict_types=1);

namespace Tests\Unit;

use Blog\Markdown;
use PHPUnit\Framework\TestCase;

/** ponytail markdown renderer for blog post HTML output. */
final class MarkdownTest extends TestCase
{
    /** Paragraph breaks and inline images render as safe HTML. */
    public function test_paragraphs_and_images(): void
    {
        $html = Markdown::toHtml("Line one\n\n![diagram](/uploads/blog/x.png)\n\nLine two");
        $this->assertStringContainsString('<p class="blog-p">', $html);
        $this->assertStringContainsString('<img src="/uploads/blog/x.png"', $html);
        $this->assertStringContainsString('Line two', $html);
    }

    /** Script injection in markdown is escaped, not executed. */
    public function test_escapes_unsafe_html(): void
    {
        $html = Markdown::toHtml('<script>alert(1)</script>');
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    /** Headings and list blocks get semantic tags for readable posts. */
    public function test_headings_and_lists(): void
    {
        $html = Markdown::toHtml("## Section\n\n- one\n- two");
        $this->assertStringContainsString('<h2', $html);
        $this->assertStringContainsString('<ul class="blog-ul">', $html);
    }
}
