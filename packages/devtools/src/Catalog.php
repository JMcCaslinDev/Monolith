<?php

declare(strict_types=1);

namespace Devtools;

final class Catalog
{
    /** @return list<array{id: string, slug: string, name: string, category: string, category_label: string, icon: string}> */
    public static function tools(): array
    {
        $out = [];
        foreach (self::categories() as $catId => $cat) {
            foreach ($cat['tools'] as $tool) {
                $out[] = [
                    'id' => $catId . '.' . $tool['slug'],
                    'slug' => $tool['slug'],
                    'name' => $tool['name'],
                    'category' => $catId,
                    'category_label' => $cat['label'],
                    'icon' => $tool['icon'],
                ];
            }
        }
        return $out;
    }

    /** @return array<string, array{label: string, icon: string, tools: list<array{slug: string, name: string, icon: string}>}> */
    public static function categories(): array
    {
        return [
            'converters' => [
                'label' => 'Converters',
                'icon' => '↻',
                'tools' => [
                    ['slug' => 'cron-parser', 'name' => 'Cron parser', 'icon' => '⏱'],
                    ['slug' => 'date', 'name' => 'Date', 'icon' => '📅'],
                    ['slug' => 'json-table', 'name' => 'JSON > Table', 'icon' => '▦'],
                    ['slug' => 'json-yaml', 'name' => 'JSON <> YAML', 'icon' => '⇄'],
                    ['slug' => 'number-base', 'name' => 'Number Base', 'icon' => '#'],
                ],
            ],
            'encoders' => [
                'label' => 'Encoders / Decoders',
                'icon' => '01',
                'tools' => [
                    ['slug' => 'base64-image', 'name' => 'Base64 Image', 'icon' => '64'],
                    ['slug' => 'base64-text', 'name' => 'Base64 Text', 'icon' => '64'],
                    ['slug' => 'certificate', 'name' => 'Certificate', 'icon' => '📜'],
                    ['slug' => 'gzip', 'name' => 'GZip', 'icon' => 'zip'],
                    ['slug' => 'html', 'name' => 'HTML', 'icon' => '5'],
                    ['slug' => 'jwt', 'name' => 'JWT', 'icon' => '◎'],
                    ['slug' => 'qr-code', 'name' => 'QR Code', 'icon' => '▣'],
                    ['slug' => 'url', 'name' => 'URL', 'icon' => '🔗'],
                ],
            ],
            'formatters' => [
                'label' => 'Formatters',
                'icon' => '≡',
                'tools' => [
                    ['slug' => 'json', 'name' => 'JSON', 'icon' => '{}'],
                    ['slug' => 'sql', 'name' => 'SQL', 'icon' => '▤'],
                    ['slug' => 'xml', 'name' => 'XML', 'icon' => '</>'],
                ],
            ],
            'generators' => [
                'label' => 'Generators',
                'icon' => '⚙',
                'tools' => [
                    ['slug' => 'hash', 'name' => 'Hash / Checksum', 'icon' => '☷'],
                    ['slug' => 'lorem-ipsum', 'name' => 'Lorem Ipsum', 'icon' => 'Li'],
                    ['slug' => 'password', 'name' => 'Password', 'icon' => '**'],
                    ['slug' => 'uuid', 'name' => 'UUID', 'icon' => 'ID'],
                ],
            ],
            'graphic' => [
                'label' => 'Graphic',
                'icon' => '🖼',
                'tools' => [
                    ['slug' => 'color-blindness', 'name' => 'Color Blindness Simulator', 'icon' => '👁'],
                    ['slug' => 'image-converter', 'name' => 'Image Converter', 'icon' => '🖼'],
                ],
            ],
            'testers' => [
                'label' => 'Testers',
                'icon' => '✓',
                'tools' => [
                    ['slug' => 'jsonpath', 'name' => 'JSONPath', 'icon' => '{}'],
                    ['slug' => 'regex', 'name' => 'RegEx', 'icon' => '(.*)'],
                    ['slug' => 'xml-tester', 'name' => 'XML', 'icon' => '✓'],
                ],
            ],
            'text' => [
                'label' => 'Text',
                'icon' => 'Aa',
                'tools' => [
                    ['slug' => 'escape-unescape', 'name' => 'Escape / Unescape', 'icon' => 'T'],
                    ['slug' => 'list-compare', 'name' => 'List Compare', 'icon' => '▥'],
                    ['slug' => 'markdown-preview', 'name' => 'Markdown Preview', 'icon' => 'M↓'],
                    ['slug' => 'text-analyzer', 'name' => 'Analyzer & Utilities', 'icon' => 'T'],
                    ['slug' => 'text-compare', 'name' => 'Compare', 'icon' => '▥'],
                ],
            ],
        ];
    }

  /** @return array{id: string, slug: string, name: string, category: string, category_label: string, icon: string}|null */
    public static function find(string $slug): ?array
    {
        foreach (self::tools() as $tool) {
            if ($tool['slug'] === $slug) {
                return $tool;
            }
        }
        return null;
    }

    public static function categoryPermission(string $category): string
    {
        return 'devtools.' . $category . '.use';
    }

    public static function toolPermission(string $category, string $slug): string
    {
        return 'devtools.' . $category . '.' . $slug . '.use';
    }

    /** @param list<string> $userPermissions */
    public static function canUse(string $slug, array $userPermissions): bool
    {
        $tool = self::find($slug);
        if ($tool === null) {
            return false;
        }
        $catPerm = self::categoryPermission($tool['category']);
        $toolPerm = self::toolPermission($tool['category'], $tool['slug']);
        return in_array($toolPerm, $userPermissions, true)
            || in_array($catPerm, $userPermissions, true);
    }

    /** @param list<string> $userPermissions */
    public static function accessibleTools(array $userPermissions): array
    {
        return array_values(array_filter(
            self::tools(),
            fn (array $t) => self::canUse($t['slug'], $userPermissions)
        ));
    }

    /** @param list<string> $userPermissions */
    public static function accessibleCategories(array $userPermissions): array
    {
        $cats = [];
        foreach (self::categories() as $catId => $cat) {
            $tools = array_values(array_filter(
                $cat['tools'],
                fn (array $t) => self::canUse($t['slug'], $userPermissions)
            ));
            if ($tools !== []) {
                $cats[$catId] = array_merge($cat, ['tools' => $tools]);
            }
        }
        return $cats;
    }
}
