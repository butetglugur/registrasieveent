<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    private static ?Session $instance = null;
    private bool $started = false;

    public static function instance(): Session
    {
        if (self::$instance === null) {
            self::$instance = new Session();
        }
        return self::$instance;
    }

    public function start(): void
    {
        if ($this->started) {
            return;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            $this->ageFlash();
            return;
        }
        $req = Request::current();
        $path = Request::basePath();
        session_name((string) config('app.session_name', 'presensi_session'));
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        if (PHP_VERSION_ID < 80400) {
            ini_set('session.sid_length', '48');
            ini_set('session.sid_bits_per_character', '6');
        }
        ini_set('session.gc_maxlifetime', (string) (max(30, (int) config('app.session_lifetime', 120)) * 60));
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');

        // Simpan sesi di folder aplikasi sendiri (lebih aman di shared hosting).
        $dir = storage_path('sessions');
        if (is_dir($dir) && is_writable($dir)) {
            session_save_path($dir);
        }

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => $path === '' ? '/' : $path . '/',
            'secure'   => $req->isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        if (headers_sent()) {
            // CLI / pengujian
            $_SESSION = $_SESSION ?? [];
        } else {
            session_start();
        }
        $this->started = true;
        $this->ageFlash();
    }

    private function ageFlash(): void
    {
        $_SESSION['_flash_old'] = $_SESSION['_flash_new'] ?? [];
        $_SESSION['_flash_new'] = [];
    }

    /** @return mixed */
    public function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public function put(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /** @return mixed */
    public function pull(string $key, $default = null)
    {
        $v = $_SESSION[$key] ?? $default;
        unset($_SESSION[$key]);
        return $v;
    }

    public function flash(string $key, $value): void
    {
        $_SESSION['_flash_new'][$key] = $value;
    }

    /** @return mixed */
    public function getFlash(string $key, $default = null)
    {
        if (isset($_SESSION['_flash_new']) && array_key_exists($key, $_SESSION['_flash_new'])) {
            return $_SESSION['_flash_new'][$key];
        }
        if (isset($_SESSION['_flash_old']) && array_key_exists($key, $_SESSION['_flash_old'])) {
            return $_SESSION['_flash_old'][$key];
        }
        return $default;
    }

    /** Pertahankan flash lama untuk satu request lagi. */
    public function reflash(): void
    {
        $_SESSION['_flash_new'] = array_merge($_SESSION['_flash_old'] ?? [], $_SESSION['_flash_new'] ?? []);
    }

    public function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_regenerate_id(true);
        }
        unset($_SESSION['_csrf']);
    }

    public function invalidate(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'],
                'secure'   => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'] ?? 'Lax',
            ]);
            session_destroy();
            session_start();
            session_regenerate_id(true);
        }
        $_SESSION['_flash_new'] = [];
        $_SESSION['_flash_old'] = [];
    }

    public function csrfToken(): string
    {
        if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public function verifyCsrf(?string $token): bool
    {
        $known = $_SESSION['_csrf'] ?? '';
        return is_string($token) && $token !== '' && is_string($known) && $known !== '' && hash_equals($known, $token);
    }
}
