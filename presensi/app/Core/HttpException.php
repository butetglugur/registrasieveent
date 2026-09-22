<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class HttpException extends RuntimeException
{
    public int $status;

    public function __construct(int $status, string $message = '')
    {
        $this->status = $status;
        parent::__construct($message !== '' ? $message : self::defaultMessage($status), $status);
    }

    public static function defaultMessage(int $status): string
    {
        $map = [
            400 => 'Permintaan tidak valid.',
            403 => 'Anda tidak memiliki akses ke halaman ini.',
            404 => 'Halaman yang Anda cari tidak ditemukan.',
            405 => 'Metode tidak diizinkan.',
            419 => 'Sesi formulir sudah kedaluwarsa. Silakan muat ulang halaman lalu coba lagi.',
            429 => 'Terlalu banyak permintaan. Silakan tunggu sebentar lalu coba lagi.',
            500 => 'Terjadi kesalahan pada server.',
            503 => 'Layanan sedang tidak tersedia.',
        ];
        return $map[$status] ?? 'Terjadi kesalahan.';
    }
}
