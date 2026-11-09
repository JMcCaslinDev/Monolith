<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Fixed-window counter kept in per-key files. State is per instance, which bounds abuse of a single
 * task without needing Redis; it is not a global quota across tasks.
 */
final class RateLimiter
{
    public function __construct(private string $dir)
    {
    }

    /** Records a hit and returns true while the key is within its limit for the current window. */
    public function allow(string $key, int $maxHits, int $windowSeconds, ?int $now = null): bool
    {
        $now ??= time();
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0700, true) && !is_dir($this->dir)) {
            return true;
        }
        $handle = fopen($this->dir . '/' . hash('sha256', $key), 'c+');
        if ($handle === false) {
            return true;
        }
        try {
            flock($handle, LOCK_EX);
            $raw = trim((string) stream_get_contents($handle));
            [$start, $count] = array_map('intval', explode(' ', $raw . ' 0') + [0, 0]);
            if ($raw === '' || $now - $start >= $windowSeconds) {
                $start = $now;
                $count = 0;
            }
            $count++;
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, "{$start} {$count}");
            return $count <= $maxHits;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
