<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class AuthMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (!Auth::check()) {
            if ($request->wantsJson()) {
                return Response::json(['ok' => false, 'message' => 'Sesi login berakhir. Silakan login ulang.'], 401);
            }
            if (!$request->isPost()) {
                Session::instance()->put('intended', $request->fullUrl());
            }
            return Response::redirect(route('login'));
        }
        // Kirim ulang notifikasi yang tertunda/gagal (retry) setelah halaman admin terkirim.
        if (\App\Services\Notifier::enabled()) {
            \App\Core\App::terminating(static function () {
                \App\Services\Notifier::process([], 3);
            });
        }
        return $next($request);
    }
}
