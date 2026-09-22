<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    private static ?Request $current = null;
    private static ?string $basePath = null;

    /** @var array<string,string> */
    public array $params = [];
    private string $method;
    private string $path;

    public function __construct()
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $this->method = $method === 'HEAD' ? 'GET' : $method;
        $this->path = self::resolvePath();
    }

    public static function current(): Request
    {
        if (self::$current === null) {
            self::$current = new Request();
        }
        return self::$current;
    }

    /** Untuk pengujian. */
    public static function reset(): void
    {
        self::$current = null;
        self::$basePath = null;
    }

    /**
     * Deteksi subfolder instalasi. Mendukung:
     *  - public/ dijadikan document root
     *  - seluruh aplikasi di public_html (root .htaccess me-rewrite ke public/)
     *  - instalasi di subfolder, mis. /presensi
     */
    public static function basePath(): string
    {
        if (self::$basePath !== null) {
            return self::$basePath;
        }
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
        if ($dir === '.' || $dir === '/') {
            $dir = '';
        }
        $uri = self::uriPath();
        if (str_ends_with($dir, '/public') && !($uri === $dir || str_starts_with($uri, $dir . '/'))) {
            $dir = substr($dir, 0, -7);
        }
        return self::$basePath = $dir;
    }

    private static function uriPath(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? rawurldecode($path) : '/';
        return $path === '' ? '/' : $path;
    }

    private static function resolvePath(): string
    {
        $path = self::uriPath();
        $base = self::basePath();
        if ($base !== '' && ($path === $base || str_starts_with($path, $base . '/'))) {
            $path = substr($path, strlen($base));
        }
        if (str_starts_with($path, '/index.php')) {
            $path = substr($path, 10);
        }
        $path = '/' . trim((string) $path, '/');
        return $path;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function path(): string
    {
        return $this->path;
    }

    /** @return mixed */
    public function input(string $key, $default = null)
    {
        if (array_key_exists($key, $_POST)) {
            return $_POST[$key];
        }
        return $_GET[$key] ?? $default;
    }

    public function str(string $key, string $default = ''): string
    {
        $v = $this->input($key, $default);
        if (is_array($v)) {
            return $default;
        }
        // Buang karakter kontrol, rapikan spasi.
        $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $v) ?? '';
        return trim($v);
    }

    public function int(string $key, int $default = 0): int
    {
        $v = $this->input($key);
        return is_scalar($v) && is_numeric($v) ? (int) $v : $default;
    }

    public function bool(string $key): bool
    {
        $v = $this->input($key);
        return in_array($v, ['1', 'on', 'true', 'yes', 1, true], true);
    }

    public function arr(string $key): array
    {
        $v = $this->input($key, []);
        return is_array($v) ? $v : [];
    }

    public function query(string $key, string $default = ''): string
    {
        $v = $_GET[$key] ?? $default;
        return is_string($v) ? trim($v) : $default;
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    /** @param string[] $keys */
    public function only(array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = $this->str($k);
        }
        return $out;
    }

    public function param(string $key, string $default = ''): string
    {
        return $this->params[$key] ?? $default;
    }

    public function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
            return true;
        }
        $proto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        return $proto === 'https';
    }

    public function host(): string
    {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
        // Validasi header Host agar tidak bisa dipakai untuk injeksi.
        if (!preg_match('/^[a-z0-9.\-]+(:\d{1,5})?$/i', $host)) {
            $host = (string) ($_SERVER['SERVER_NAME'] ?? 'localhost');
        }
        return strtolower(preg_replace('/:\d+$/', '', $host) ?? $host);
    }

    public function origin(): string
    {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        if (!preg_match('/^[a-z0-9.\-]+(:\d{1,5})?$/i', $host)) {
            $host = $this->host();
        }
        return ($this->isHttps() ? 'https' : 'http') . '://' . strtolower($host);
    }

    public function wantsJson(): bool
    {
        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        $xrw = (string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
        return str_contains($accept, 'application/json') || strcasecmp($xrw, 'XMLHttpRequest') === 0;
    }

    public function userAgent(): string
    {
        return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public function fullUrl(): string
    {
        return (string) ($_SERVER['REQUEST_URI'] ?? '/');
    }
}
