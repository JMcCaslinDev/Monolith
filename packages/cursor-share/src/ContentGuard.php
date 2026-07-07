<?php

declare(strict_types=1);

namespace CursorShare;

use JsonException;

/** Profanity and injection screening for community Cursor asset posts. */
final class ContentGuard
{
    public const MAX_BYTES = 262144;

    /** ponytail: small inline list; swap for external list if moderation needs grow */
    private const BAD_WORDS = [
        'fuck', 'shit', 'asshole', 'bitch', 'cunt', 'nigger', 'faggot', 'retard',
    ];

    /** @return array{ok: bool, error?: string, content?: string} */
    public static function validatePost(
        string $category,
        string $title,
        string $description,
        string $filename,
        string $content,
    ): array {
        if (!Catalog::isCategory($category)) {
            return ['ok' => false, 'error' => 'Invalid category'];
        }

        $title = trim($title);
        if ($title === '' || strlen($title) > 200) {
            return ['ok' => false, 'error' => 'Title must be 1–200 characters'];
        }

        $description = trim($description);
        if (strlen($description) > 1000) {
            return ['ok' => false, 'error' => 'Description must be at most 1000 characters'];
        }

        $filename = Catalog::normalizeFilename($category, $filename);
        if (strlen($filename) > 255) {
            return ['ok' => false, 'error' => 'Filename is too long'];
        }

        if ($content === '') {
            return ['ok' => false, 'error' => 'Content is required'];
        }
        if (strlen($content) > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'Content exceeds ' . self::MAX_BYTES . ' bytes'];
        }

        foreach ([$title, $description, $content] as $field) {
            if (self::containsBadWord($field)) {
                return ['ok' => false, 'error' => 'Content contains blocked language'];
            }
        }

        if (self::containsInjection($content)) {
            return ['ok' => false, 'error' => 'Content contains disallowed script or code patterns'];
        }

        if ($category === 'hooks') {
            try {
                $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return ['ok' => false, 'error' => 'Hooks content must be valid JSON'];
            }
            if (!is_array($decoded)) {
                return ['ok' => false, 'error' => 'Hooks JSON must be an object or array'];
            }
        }

        if ($category === 'skills' && !str_ends_with(strtolower($filename), 'skill.md')) {
            return ['ok' => false, 'error' => 'Skills must use a SKILL.md filename'];
        }

        return [
            'ok' => true,
            'content' => $content,
            'title' => $title,
            'description' => $description,
            'filename' => $filename,
        ];
    }

    public static function containsBadWord(string $text): bool
    {
        $lower = strtolower($text);
        foreach (self::BAD_WORDS as $word) {
            if (preg_match('/\b' . preg_quote($word, '/') . '\b/i', $lower)) {
                return true;
            }
        }

        return false;
    }

    public static function containsInjection(string $text): bool
    {
        $patterns = [
            '/<script\b/i',
            '/javascript\s*:/i',
            '/on\w+\s*=/i',
            '/<\?php/i',
            '/<\?=/i',
            '/<iframe\b/i',
            '/data:text\/html/i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }
}
