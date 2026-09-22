<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Validator;
use App\Core\ValidationException;

abstract class Controller
{
    /**
     * Validasi; bila gagal, kembali ke form dengan pesan error & input lama.
     * @return array<string,mixed> data yang divalidasi (hanya key di $rules)
     */
    protected function validate(array $data, array $rules, array $labels = [], string $redirectTo = ''): array
    {
        $v = Validator::make($data, $rules, $labels);
        if ($v->fails()) {
            throw new ValidationException($v->errors(), $data, $redirectTo);
        }
        return array_intersect_key($data, $rules);
    }
}
