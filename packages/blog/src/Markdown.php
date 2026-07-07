<?php

declare(strict_types=1);

namespace Blog;

/** ponytail: minimal markdown → safe HTML for blog posts (no external parser). */
final class Markdown
{
    public static function toHtml(string $markdown): string
    {
        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
        $parts = preg_split("/\n{2,}/", $markdown) ?: [];

        $html = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            if (preg_match('/^```/', $part)) {
                $html[] = '<pre class="blog-pre"><code>' . self::escape($part) . '</code></pre>';
                continue;
            }

            if (preg_match('/^(#{1,3})\s+(.+)$/m', $part, $m)) {
                $level = strlen($m[1]);
                $html[] = sprintf('<h%d class="blog-h%d">%s</h%d>', $level, $level, self::inline($m[2]), $level);
                continue;
            }

            if (preg_match('/^!\[([^\]]*)\]\(([^)]+)\)$/', $part, $m)) {
                $html[] = self::imageTag($m[2], $m[1]);
                continue;
            }

            $lines = explode("\n", $part);
            if (count($lines) > 1 && self::isListBlock($lines)) {
                $html[] = self::listHtml($lines);
                continue;
            }

            $inner = implode("<br>\n", array_map(
                fn (string $line) => self::inline($line),
                $lines
            ));
            $html[] = '<p class="blog-p">' . $inner . '</p>';
        }

        return implode("\n", $html);
    }

    /** @param list<string> $lines */
    private static function isListBlock(array $lines): bool
    {
        foreach ($lines as $line) {
            if (!preg_match('/^[-*]\s+/', trim($line))) {
                return false;
            }
        }

        return true;
    }

    /** @param list<string> $lines */
    private static function listHtml(array $lines): string
    {
        $items = [];
        foreach ($lines as $line) {
            $line = preg_replace('/^[-*]\s+/', '', trim($line)) ?? '';
            $items[] = '<li>' . self::inline($line) . '</li>';
        }

        return '<ul class="blog-ul">' . implode('', $items) . '</ul>';
    }

    private static function inline(string $text): string
    {
        $text = self::escape($text);
        $text = preg_replace('/!\[([^\]]*)\]\(([^)]+)\)/', '<span data-img-placeholder>$0</span>', $text) ?? $text;
        $text = preg_replace_callback(
            '/<span data-img-placeholder>!\[([^\]]*)\]\(([^)]+)\)<\/span>/',
            fn (array $m) => self::imageTag($m[2], $m[1]),
            $text
        ) ?? $text;
        $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" rel="noopener">$1</a>', $text) ?? $text;
        $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text) ?? $text;
        $text = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $text) ?? $text;
        $text = preg_replace('/`([^`]+)`/', '<code class="blog-code">$1</code>', $text) ?? $text;

        return $text;
    }

    private static function imageTag(string $url, string $alt): string
    {
        $url = trim($url);
        if (!self::isSafeUrl($url)) {
            return '';
        }

        return sprintf(
            '<img src="%s" alt="%s" class="blog-img" loading="lazy">',
            self::escape($url),
            self::escape($alt)
        );
    }

    private static function isSafeUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }
        if (str_starts_with($url, '/')) {
            return !str_contains($url, '..');
        }

        return (bool) preg_match('#^https?://#i', $url);
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
