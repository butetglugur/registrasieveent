<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Enkripsi AES-256-GCM untuk rahasia yang disimpan di database
 * (token API WhatsApp, password SMTP, secret webhook). Kunci = APP_KEY.
 */
final class Crypt
{
    private const PREFIX = 'enc:v1:';

    private static function key(): string
    {
        $k = (string) config('app.key', '');
        if ($k === '') {
            throw new \RuntimeException('APP_KEY belum diset');
        }
        return hash('sha256', 'crypt|' . $k, true);
    }

    public static function encrypt(string $plain): string
    {
        if ($plain === '') {
            return '';
        }
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new \RuntimeException('Enkripsi gagal');
        }
        return self::PREFIX . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(?string $value): string
    {
        $value = (string) $value;
        if ($value === '' || !str_starts_with($value, self::PREFIX)) {
            return '';
        }
        $raw = base64_decode(substr($value, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < 29) {
            return '';
        }
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $plain === false ? '' : $plain;
    }
}
