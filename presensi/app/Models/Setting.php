<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class Setting
{
    /** @var array<string,string|null>|null */
    private static ?array $cache = null;

    public const DEFAULTS = [
        'app_name'          => 'Presensi Event',
        'org_name'          => '',
        'tagline'           => 'Daftar hadir digital yang cepat, rapi, dan tanpa antre.',
        'footer_text'       => '',
        'home_mode'         => 'list',       // list | event
        'home_event_id'     => '',
        'wa_country_code'   => '62',
        'submit_limit'      => '30',         // pendaftaran per IP per 10 menit
        'default_theme'     => 'violet',
        'show_count_public' => '1',
    ];

    private static function load(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            try {
                foreach (DB::select('SELECT `key`, value FROM app_settings') as $row) {
                    self::$cache[(string) $row['key']] = $row['value'];
                }
            } catch (\Throwable $e) {
                self::$cache = [];
            }
        }
        return self::$cache;
    }

    /** @return mixed */
    public static function get(string $key, $default = null)
    {
        $all = self::load();
        if (array_key_exists($key, $all) && $all[$key] !== null) {
            return $all[$key];
        }
        return $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function set(string $key, ?string $value): void
    {
        DB::run(
            'INSERT INTO app_settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?',
            [$key, $value, $value]
        );
        self::load();
        self::$cache[$key] = $value;
    }

    /** @param array<string,string|null> $values */
    public static function setMany(array $values): void
    {
        DB::transaction(static function () use ($values) {
            foreach ($values as $k => $v) {
                self::set((string) $k, $v === null ? null : (string) $v);
            }
        });
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
