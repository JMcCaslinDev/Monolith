<?php

declare(strict_types=1);

namespace Stickies;

/** Allowed sticky note background colors (board + editor). */
final class StickyColors
{
    /** @var list<string> */
    public const ALL = ['yellow', 'pink', 'blue', 'green', 'purple', 'orange'];

    public static function isValid(string $color): bool
    {
        return in_array($color, self::ALL, true);
    }

    /** @return array<string, string> color => Tailwind-ish hex for inline styles */
    public static function palette(): array
    {
        return [
            'yellow' => '#fef08a',
            'pink' => '#fbcfe8',
            'blue' => '#bfdbfe',
            'green' => '#bbf7d0',
            'purple' => '#e9d5ff',
            'orange' => '#fed7aa',
        ];
    }
}
