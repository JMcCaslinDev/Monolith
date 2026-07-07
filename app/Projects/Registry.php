<?php

declare(strict_types=1);

namespace App\Projects;

final class Registry
{
    /** @var array<string, array<string, mixed>>|null */
    private static ?array $packages = null;

    /** @return array<string, array<string, mixed>> */
    public static function packages(): array
    {
        if (self::$packages !== null) {
            return self::$packages;
        }

        self::$packages = [];
        $root = dirname(__DIR__, 2);
        foreach (glob($root . '/packages/*/manifest.php') ?: [] as $file) {
            /** @var array<string, mixed> $manifest */
            $manifest = require $file;
            $id = (string) ($manifest['id'] ?? basename(dirname($file)));
            $manifest['_path'] = dirname($file);
            self::$packages[$id] = $manifest;
        }

        return self::$packages;
    }

    /** @return list<array<string, mixed>> */
    public static function projects(): array
    {
        $out = [];
        foreach (self::packages() as $pkg) {
            if (!isset($pkg['project']) || !is_array($pkg['project'])) {
                continue;
            }
            $out[] = array_merge($pkg['project'], ['id' => $pkg['id']]);
        }
        return $out;
    }

    /** @param list<string> $userPermissions */
    public static function visibleProjects(array $userPermissions): array
    {
        return array_values(array_filter(
            self::projects(),
            fn (array $p) => in_array($p['permissions']['view'] ?? '', $userPermissions, true)
        ));
    }

    /** @param list<string> $userPermissions */
    public static function openableProjects(array $userPermissions): array
    {
        return array_values(array_filter(
            self::projects(),
            fn (array $p) => in_array($p['permissions']['open'] ?? '', $userPermissions, true)
        ));
    }

    /** @return list<array<string, mixed>> */
    public static function packagePermissions(): array
    {
        $perms = [];
        foreach (self::packages() as $pkg) {
            foreach ($pkg['permissions'] ?? [] as $perm) {
                if (is_array($perm) && isset($perm['name'])) {
                    $perms[] = $perm;
                }
            }
        }
        return $perms;
    }

    /** @return list<array<string, mixed>> */
    public static function packageEvents(): array
    {
        $events = [];
        foreach (self::packages() as $pkg) {
            foreach ($pkg['events'] ?? [] as $event) {
                if (is_array($event) && isset($event['type'])) {
                    $events[] = $event;
                }
            }
        }
        return $events;
    }

    /** ponytail: test-only — clears cached package manifests */
    public static function resetForTests(): void
    {
        self::$packages = null;
    }

    /** @return list<array<string, mixed>> */
    public static function packageRoutes(): array
    {
        $routes = [];
        foreach (self::packages() as $pkg) {
            foreach ($pkg['routes'] ?? [] as $route) {
                if (is_array($route)) {
                    $routes[] = $route;
                }
            }
        }
        return $routes;
    }
}
