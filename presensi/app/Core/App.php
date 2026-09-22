<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\AdminMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\GuestMiddleware;
use Throwable;

final class App
{
    /** @var array<string,class-string> */
    private array $middlewareMap = [
        'auth'  => AuthMiddleware::class,
        'guest' => GuestMiddleware::class,
        'admin' => AdminMiddleware::class,
    ];

    /** @var callable[] */
    private static array $terminating = [];

    /** Jalankan tugas setelah respons terkirim (mis. kirim notifikasi). */
    public static function terminating(callable $fn): void
    {
        self::$terminating[] = $fn;
    }

    public function run(): void
    {
        $response = $this->handle(Request::current());
        $this->applySecurityHeaders($response);
        $response->send();
        $this->terminate();
    }

    private function terminate(): void
    {
        if (!self::$terminating) {
            return;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        // Putus koneksi ke browser dulu (PHP-FPM / LiteSpeed) agar pengunjung tidak menunggu.
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        } else {
            @ob_end_flush();
            @flush();
        }
        ignore_user_abort(true);
        foreach (self::$terminating as $fn) {
            try {
                $fn();
            } catch (Throwable $e) {
                ErrorHandler::log($e);
            }
        }
        self::$terminating = [];
    }

    public function handle(Request $request): Response
    {
        try {
            $path = $request->path();
            $installed = is_installed();

            if (!$installed && !str_starts_with($path, '/install')) {
                return Response::redirect(url('install'));
            }

            if ($installed) {
                Migrator::ensure();
            }

            Session::instance()->start();

            if ($request->isPost()) {
                $this->verifyCsrf($request);
            }

            $router = Router::instance();
            if (!$router->routes()) {
                $r = $router;
                require base_path('routes/web.php');
            }

            [$route, $params] = $router->match($request->method(), $path);
            $request->params = $params;

            $core = function (Request $req) use ($route): Response {
                return $this->callAction($route['action'], $req);
            };

            $pipeline = array_reduce(
                array_reverse($route['middleware']),
                function (callable $next, string $name) {
                    return function (Request $req) use ($next, $name): Response {
                        $class = $this->middlewareMap[$name] ?? null;
                        if ($class === null) {
                            throw new \RuntimeException('Middleware tidak dikenal: ' . $name);
                        }
                        return (new $class())->handle($req, $next);
                    };
                },
                $core
            );

            return $pipeline($request);
        } catch (ValidationException $e) {
            if ($request->wantsJson()) {
                return Response::json(['ok' => false, 'message' => $e->getMessage(), 'errors' => $e->errors], 422);
            }
            $res = $e->redirectTo !== '' ? Response::redirect($e->redirectTo) : back();
            return $res->withErrors($e->errors, $e->old)
                ->with('error', 'Periksa kembali isian Anda. ' . count($e->errors) . ' kolom perlu diperbaiki.');
        } catch (HttpException $e) {
            // Token formulir kedaluwarsa: kembalikan ke form dengan pesan ramah.
            if ($e->status === 419 && !$request->wantsJson() && !empty($_SERVER['HTTP_REFERER'])) {
                return back()->with('error', $e->getMessage());
            }
            return ErrorHandler::httpResponse($e, $request);
        } catch (Throwable $e) {
            ErrorHandler::log($e);
            return ErrorHandler::serverErrorResponse($e, $request);
        }
    }

    private function verifyCsrf(Request $request): void
    {
        // Tolak POST lintas situs berdasarkan header Origin (bila ada).
        $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        if ($origin !== '' && $origin !== 'null') {
            $host = parse_url($origin, PHP_URL_HOST);
            if (!is_string($host) || strcasecmp($host, $request->host()) !== 0) {
                throw new HttpException(403, 'Permintaan lintas situs ditolak.');
            }
        }
        $token = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!Session::instance()->verifyCsrf(is_string($token) ? $token : null)) {
            throw new HttpException(419);
        }
    }

    /** @param mixed $action */
    private function callAction($action, Request $request): Response
    {
        if (is_array($action) && count($action) === 2) {
            [$class, $method] = $action;
            $controller = new $class();
            $result = $controller->{$method}($request, ...array_values($request->params));
        } elseif (is_callable($action)) {
            $result = $action($request, ...array_values($request->params));
        } else {
            throw new \RuntimeException('Aksi route tidak valid');
        }
        if ($result instanceof Response) {
            return $result;
        }
        return new Response((string) $result, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    private function applySecurityHeaders(Response $response): void
    {
        $nonce = csp_nonce();
        $defaults = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options'        => 'SAMEORIGIN',
            'Referrer-Policy'        => 'strict-origin-when-cross-origin',
            'Permissions-Policy'     => 'camera=(self), microphone=(), geolocation=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Content-Security-Policy' => "default-src 'self'; "
                . "script-src 'self' 'nonce-" . $nonce . "'; "
                . "style-src 'self' 'unsafe-inline'; "
                . "font-src 'self' data:; "
                . "img-src 'self' data: blob:; "
                . "connect-src 'self'; media-src 'self' blob:; "
                . "frame-ancestors 'self'; form-action 'self'; base-uri 'self'; object-src 'none'",
        ];
        if (Request::current()->isHttps()) {
            $defaults['Strict-Transport-Security'] = 'max-age=15552000';
        }
        foreach ($defaults as $k => $v) {
            if (!isset($response->headers[$k])) {
                $response->headers[$k] = $v;
            }
        }
        if (!isset($response->headers['Content-Type'])) {
            $response->headers['Content-Type'] = 'text/html; charset=utf-8';
        }
        if (!isset($response->headers['Cache-Control']) && Session::instance()->get('auth_id')) {
            $response->headers['Cache-Control'] = 'no-store, private';
        }
        if (function_exists('header_remove') && !headers_sent()) {
            header_remove('X-Powered-By');
        }
    }
}
