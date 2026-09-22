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
        return $next($request);
    }
}
