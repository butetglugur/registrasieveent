<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class User
{
    public const ROLES = [
        'admin' => 'Administrator',
        'staff' => 'Staf (check-in & lihat data)',
    ];

    public static function find(int $id): ?array
    {
        return DB::first('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public static function findByUsername(string $username): ?array
    {
        return DB::first('SELECT * FROM users WHERE username = ?', [$username]);
    }

    public static function all(): array
    {
        return DB::select('SELECT * FROM users ORDER BY role = \'admin\' DESC, name ASC');
    }

    public static function create(array $data): int
    {
        $now = now();
        return DB::insert('users', [
            'name'          => $data['name'],
            'username'      => $data['username'],
            'email'         => $data['email'] ?: null,
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'role'          => isset(self::ROLES[$data['role'] ?? '']) ? $data['role'] : 'staff',
            'is_active'     => 1,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
    }

    public static function update(int $id, array $data): void
    {
        $data['updated_at'] = now();
        DB::update('users', $data, 'id = ?', [$id]);
    }

    public static function setPassword(int $id, string $password): void
    {
        self::update($id, ['password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
    }

    public static function delete(int $id): void
    {
        DB::delete('users', 'id = ?', [$id]);
    }

    public static function countAdmins(): int
    {
        return (int) DB::value("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1");
    }

    /**
     * Verifikasi login dengan waktu respons konstan (mencegah user enumeration).
     */
    public static function attempt(string $username, string $password): ?array
    {
        $user = self::findByUsername($username);
        $hash = $user['password_hash'] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
        $ok = password_verify($password, (string) $hash);
        if (!$user || !$ok || !(int) $user['is_active']) {
            return null;
        }
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            self::setPassword((int) $user['id'], $password);
            $user = self::find((int) $user['id']);
        }
        return $user;
    }
}
