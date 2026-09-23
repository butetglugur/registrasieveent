<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Setting;

/**
 * Migrasi otomatis untuk instalasi lama: cukup upload file versi baru,
 * tabel/kolom baru dibuat sendiri pada request pertama.
 *
 *  v2: tabel notifications
 *  v3: pengingat H-1 (events.send_reminder, registrations.reminded_at,
 *      notifications.kind & notifications.expires_at)
 */
final class Migrator
{
    public const VERSION = 3;

    public static function ensure(): void
    {
        $current = (int) Setting::get('schema_version', '1');
        if ($current >= self::VERSION) {
            return;
        }
        DB::runSqlFile(base_path('database/schema.sql'));
        self::addColumn('events', 'send_reminder', 'TINYINT(1) NOT NULL DEFAULT 1');
        self::addColumn('registrations', 'reminded_at', 'DATETIME NULL');
        self::addColumn('notifications', 'kind', "VARCHAR(20) NOT NULL DEFAULT 'registration'");
        self::addColumn('notifications', 'expires_at', 'DATETIME NULL');
        Setting::set('schema_version', (string) self::VERSION);
    }

    public static function addColumn(string $table, string $column, string $ddl): void
    {
        $exists = (int) DB::value(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            [$table, $column]
        );
        if (!$exists) {
            DB::pdo()->exec('ALTER TABLE `' . DB::ident($table) . '` ADD COLUMN `' . DB::ident($column) . '` ' . $ddl);
        }
    }
}
