<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

/** Hanya role "admin" (bukan staf). Harus dipasang setelah "auth". */
final class AdminMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (!is_admin()) {
            throw new HttpException(403, 'Fitur ini hanya untuk Administrator.');
        }
        return $next($request);
    }
}
