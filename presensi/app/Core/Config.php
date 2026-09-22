<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Pemuat konfigurasi ala Laravel: config/*.php dengan nilai dari config/env.php.
 */
final class Config
{
    /** @var array<string,mixed> */
    private static array $items = [];
    /** @var array<string,mixed> */
    private static array $env = [];
    private static bool $hasEnv = false;

    public static function load(): void
    {
        $envFile = self::envPath();
        if (is_file($envFile)) {
            $env = require $envFile;
            if (is_array($env)) {
                self::$env = $env;
                self::$hasEnv = true;
            }
        }
        foreach (['app', 'database'] as $name) {
            $file = BASE_PATH . '/config/' . $name . '.php';
            self::$items[$name] = is_file($file) ? (array) require $file : [];
        }
    }

    public static function envPath(): string
    {
        return BASE_PATH . '/config/env.php';
    }

    public static function hasEnv(): bool
    {
        return self::$hasEnv;
    }

    /** @return mixed */
    public static function env(string $key, $default = null)
    {
        return array_key_exists($key, self::$env) ? self::$env[$key] : $default;
    }

    /** @return mixed */
    public static function get(string $key, $default = null)
    {
        $value = self::$items;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    /** @param mixed $value */
    public static function set(string $key, $value): void
    {
        $ref = &self::$items;
        foreach (explode('.', $key) as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }
        $ref = $value;
    }

    /**
     * Tulis config/env.php (dipakai installer).
     * @param array<string,mixed> $values
     */
    public static function writeEnv(array $values): bool
    {
        $content = "<?php\n// File ini dibuat otomatis oleh installer. JANGAN dibagikan ke siapa pun.\n"
            . "// Berisi kredensial database dan kunci aplikasi.\n\nreturn " . var_export($values, true) . ";\n";
        $path = self::envPath();
        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $content, LOCK_EX) === false) {
            return false;
        }
        @chmod($tmp, 0640);
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            return false;
        }
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }
        return true;
    }

    public static function renderEnv(array $values): string
    {
        return "<?php\n\nreturn " . var_export($values, true) . ";\n";
    }
}
