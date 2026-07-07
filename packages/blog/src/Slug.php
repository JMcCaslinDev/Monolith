<?php

declare(strict_types=1);

namespace Blog;

/** URL-safe slug generation for blog posts. */
final class Slug
{
    public static function fromTitle(string $title): string
    {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug !== '' ? substr($slug, 0, 120) : 'post';
    }

    public static function isValid(string $slug): bool
    {
        return $slug !== '' && strlen($slug) <= 120 && (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug);
    }
}
