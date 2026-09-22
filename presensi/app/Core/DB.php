<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;
use Throwable;

/**
 * Lapisan database tipis di atas PDO. SEMUA query memakai prepared statement.
 */
final class DB
{
    private static ?PDO $pdo = null;

    public static function connect(array $cfg): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'] ?? 'localhost',
            (int) ($cfg['port'] ?? 3306),
            $cfg['database'] ?? '',
            $cfg['charset'] ?? 'utf8mb4'
        );
        $pdo = new PDO($dsn, (string) ($cfg['username'] ?? ''), (string) ($cfg['password'] ?? ''), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
            PDO::ATTR_TIMEOUT            => 5,
        ]);
        // Samakan zona waktu MySQL dengan PHP.
        $offset = (new \DateTime('now'))->format('P');
        $pdo->exec("SET time_zone = '" . $offset . "'");
        $pdo->exec("SET SESSION sql_mode = REPLACE(@@SESSION.sql_mode, 'ONLY_FULL_GROUP_BY', '')");
        return $pdo;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = self::connect((array) config('database'));
        }
        return self::$pdo;
    }

    public static function setPdo(?PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $positional = array_values($params) === $params;
        foreach ($params as $k => $v) {
            $stmt->bindValue($positional ? $k + 1 : ':' . ltrim((string) $k, ':'), $v, self::type($v));
        }
        $stmt->execute();
        return $stmt;
    }

    private static function type($v): int
    {
        if (is_int($v)) {
            return PDO::PARAM_INT;
        }
        if (is_bool($v)) {
            return PDO::PARAM_BOOL;
        }
        if ($v === null) {
            return PDO::PARAM_NULL;
        }
        return PDO::PARAM_STR;
    }

    /** @return array<int,array<string,mixed>> */
    public static function select(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public static function first(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** @return mixed */
    public static function value(string $sql, array $params = [])
    {
        $v = self::run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = 'INSERT INTO `' . self::ident($table) . '` (`' . implode('`,`', array_map([self::class, 'ident'], $cols)) . '`) VALUES ('
            . implode(',', array_fill(0, count($cols), '?')) . ')';
        self::run($sql, array_values($data));
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $params = []): int
    {
        $sets = [];
        foreach (array_keys($data) as $col) {
            $sets[] = '`' . self::ident($col) . '` = ?';
        }
        $sql = 'UPDATE `' . self::ident($table) . '` SET ' . implode(', ', $sets) . ' WHERE ' . $where;
        return self::run($sql, array_merge(array_values($data), $params))->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        return self::run('DELETE FROM `' . self::ident($table) . '` WHERE ' . $where, $params)->rowCount();
    }

    /** @return mixed */
    public static function transaction(callable $fn)
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Placeholder "?, ?, ?" untuk klausa IN. */
    public static function placeholders(array $items): string
    {
        return implode(',', array_fill(0, max(1, count($items)), '?'));
    }

    public static function ident(string $name): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new \InvalidArgumentException('Nama kolom/tabel tidak valid: ' . $name);
        }
        return $name;
    }

    /** Escape karakter wildcard LIKE. */
    public static function like(string $term): string
    {
        return '%' . addcslashes($term, '%_\\') . '%';
    }

    public static function tableExists(string $table): bool
    {
        return (bool) self::value(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [$table]
        );
    }

    /** Jalankan file SQL (dipisah per ";" di akhir baris). */
    public static function runSqlFile(string $file): void
    {
        $sql = (string) file_get_contents($file);
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        foreach (preg_split('/;\s*[\r\n]+/', $sql) ?: [] as $statement) {
            $statement = trim($statement);
            if ($statement !== '') {
                self::pdo()->exec($statement);
            }
        }
    }
}
