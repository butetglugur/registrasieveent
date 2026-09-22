<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Setting;

/**
 * Migrasi otomatis untuk instalasi lama: cukup upload file versi baru,
 * tabel baru dibuat sendiri pada request pertama (skema memakai IF NOT EXISTS).
 */
final class Migrator
{
    public const VERSION = 2;

    public static function ensure(): void
    {
        if ((int) Setting::get('schema_version', '1') >= self::VERSION) {
            return;
        }
        DB::runSqlFile(base_path('database/schema.sql'));
        Setting::set('schema_version', (string) self::VERSION);
    }
}
