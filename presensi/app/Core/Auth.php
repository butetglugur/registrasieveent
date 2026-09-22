<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

final class Auth
{
    /** @var array<string,mixed>|null|false */
    private static $user = false;

    /** @return array<string,mixed>|null */
    public static function user(): ?array
    {
        if (self::$user !== false) {
            return self::$user;
        }
        $s = Session::instance();
        $id = (int) $s->get('auth_id', 0);
        if ($id <= 0) {
            return self::$user = null;
        }
        // Logout otomatis bila tidak aktif terlalu lama.
        $lifetime = max(5, (int) config('app.session_lifetime', 120)) * 60;
        $last = (int) $s->get('auth_last', 0);
        if ($last > 0 && time() - $last > $lifetime) {
            self::logout();
            Session::instance()->flash('warning', 'Sesi berakhir karena tidak ada aktivitas. Silakan login kembali.');
            return self::$user = null;
        }
        $user = User::find($id);
        // Sesi tidak berlaku lagi jika user dihapus/nonaktif atau password diganti.
        if ($user === null || !(int) $user['is_active'] || !hash_equals((string) $s->get('auth_hash', ''), self::fingerprint($user))) {
            self::logout();
            return self::$user = null;
        }
        $s->put('auth_last', time());
        return self::$user = $user;
    }

    public static function id(): int
    {
        $u = self::user();
        return $u ? (int) $u['id'] : 0;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function login(array $user): void
    {
        $s = Session::instance();
        $s->regenerate();
        $s->put('auth_id', (int) $user['id']);
        $s->put('auth_hash', self::fingerprint($user));
        $s->put('auth_last', time());
        self::$user = false;
    }

    public static function logout(): void
    {
        Session::instance()->invalidate();
        self::$user = null;
    }

    public static function refresh(): void
    {
        self::$user = false;
    }

    /** Sidik jari sesi — berubah bila password diganti sehingga sesi lain ter-logout. */
    public static function fingerprint(array $user): string
    {
        return hash_hmac('sha256', (string) $user['id'] . '|' . (string) $user['password_hash'], (string) config('app.key', 'presensi'));
    }
}
