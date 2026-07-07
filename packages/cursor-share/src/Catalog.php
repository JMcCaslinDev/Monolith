<?php

declare(strict_types=1);

namespace CursorShare;

/** Category metadata and filename rules for Cursor community assets. */
final class Catalog
{
    public const CATEGORIES = ['rules', 'skills', 'commands', 'hooks'];

    /** @return array<string, array{label: string, icon: string, extension: string, filename_hint: string}> */
    public static function all(): array
    {
        return [
            'rules' => [
                'label' => 'Rules',
                'icon' => '📏',
                'extension' => 'mdc',
                'filename_hint' => 'my-rule.mdc',
            ],
            'skills' => [
                'label' => 'Skills',
                'icon' => '🎯',
                'extension' => 'md',
                'filename_hint' => 'SKILL.md',
            ],
            'commands' => [
                'label' => 'Commands',
                'icon' => '⌘',
                'extension' => 'md',
                'filename_hint' => 'my-command.md',
            ],
            'hooks' => [
                'label' => 'Hooks',
                'icon' => '🪝',
                'extension' => 'json',
                'filename_hint' => 'hooks.json',
            ],
        ];
    }

    public static function isCategory(string $category): bool
    {
        return in_array($category, self::CATEGORIES, true);
    }

    /** @return array{label: string, icon: string, extension: string, filename_hint: string}|null */
    public static function category(string $category): ?array
    {
        return self::all()[$category] ?? null;
    }

    public static function normalizeFilename(string $category, string $filename): string
    {
        $filename = trim(str_replace('\\', '/', $filename));
        $filename = basename($filename);
        if ($filename === '' || str_contains($filename, '..')) {
            return self::category($category)['filename_hint'] ?? 'file.txt';
        }

        if ($category === 'skills') {
            return 'SKILL.md';
        }

        $ext = self::category($category)['extension'] ?? 'txt';
        if (!str_ends_with(strtolower($filename), '.' . $ext)) {
            $filename .= '.' . $ext;
        }

        return preg_replace('/[^a-zA-Z0-9._-]/', '-', $filename) ?: ($ext === 'md' ? 'file.md' : 'file.' . $ext);
    }
}
