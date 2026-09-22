<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Pembatas laju berbasis tabel `rate_limits` (tanpa Redis/Memcached).
 */
final class RateLimiter
{
    private static function key(string $key): string
    {
        return hash('sha256', $key);
    }

    public static function attempts(string $key): int
    {
        $row = DB::first('SELECT hits, reset_at FROM rate_limits WHERE `key` = ?', [self::key($key)]);
        if (!$row || (int) $row['reset_at'] < time()) {
            return 0;
        }
        return (int) $row['hits'];
    }

    public static function tooMany(string $key, int $max): bool
    {
        return self::attempts($key) >= $max;
    }

    public static function hit(string $key, int $decaySeconds): int
    {
        $k = self::key($key);
        $now = time();
        DB::run(
            'INSERT INTO rate_limits (`key`, hits, reset_at) VALUES (?, 1, ?)
             ON DUPLICATE KEY UPDATE
               hits = IF(reset_at < ?, 1, hits + 1),
               reset_at = IF(reset_at < ?, ?, reset_at)',
            [$k, $now + $decaySeconds, $now, $now, $now + $decaySeconds]
        );
        // Bersihkan entri kedaluwarsa sesekali.
        if (random_int(1, 50) === 1) {
            DB::run('DELETE FROM rate_limits WHERE reset_at < ?', [$now - 60]);
        }
        return self::attempts($key);
    }

    public static function availableIn(string $key): int
    {
        $row = DB::first('SELECT reset_at FROM rate_limits WHERE `key` = ?', [self::key($key)]);
        return $row ? max(0, (int) $row['reset_at'] - time()) : 0;
    }

    public static function clear(string $key): void
    {
        DB::run('DELETE FROM rate_limits WHERE `key` = ?', [self::key($key)]);
    }
}
