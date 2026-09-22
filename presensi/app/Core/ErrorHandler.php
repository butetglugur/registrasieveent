<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

final class ErrorHandler
{
    public static function register(): void
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            // Deprecation cukup dicatat, jangan sampai mematikan halaman di versi PHP yang berbeda.
            if (in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
                self::write(sprintf('[DEPRECATED] %s in %s:%d', $message, $file, $line));
                return true;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(static function (Throwable $e): void {
            self::log($e);
            $req = Request::current();
            $res = $e instanceof HttpException ? self::httpResponse($e, $req) : self::serverErrorResponse($e, $req);
            $res->send();
        });

        register_shutdown_function(static function (): void {
            $err = error_get_last();
            if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                self::write(sprintf('[FATAL] %s in %s:%d', $err['message'], $err['file'], $err['line']));
            }
        });
    }

    public static function log(Throwable $e): void
    {
        self::write(sprintf(
            "[%s] %s: %s in %s:%d\n%s",
            date('Y-m-d H:i:s'),
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        ));
    }

    public static function write(string $line): void
    {
        $dir = storage_path('logs');
        if (is_dir($dir) && is_writable($dir)) {
            @file_put_contents($dir . '/app-' . date('Y-m-d') . '.log', $line . "\n", FILE_APPEND | LOCK_EX);
        } else {
            error_log($line);
        }
    }

    public static function httpResponse(HttpException $e, Request $req): Response
    {
        if ($req->wantsJson()) {
            return Response::json(['ok' => false, 'message' => $e->getMessage()], $e->status);
        }
        return new Response(self::renderPage($e->status, $e->getMessage()), $e->status);
    }

    public static function serverErrorResponse(Throwable $e, Request $req): Response
    {
        $debug = (bool) config('app.debug');
        $message = $debug
            ? get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')'
            : 'Terjadi kesalahan pada server. Silakan coba lagi beberapa saat lagi.';
        if ($e instanceof \PDOException && !$debug) {
            $message = 'Tidak dapat terhubung atau memproses database. Silakan coba lagi nanti.';
        }
        if ($req->wantsJson()) {
            return Response::json(['ok' => false, 'message' => $message], 500);
        }
        return new Response(self::renderPage(500, $message), 500);
    }

    private static function renderPage(int $status, string $message): string
    {
        try {
            return View::make('errors.error', ['status' => $status, 'message' => $message]);
        } catch (Throwable $e) {
            return '<!doctype html><meta charset="utf-8"><title>' . $status . '</title>'
                . '<div style="font-family:sans-serif;padding:40px;text-align:center"><h1>' . $status . '</h1><p>'
                . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p></div>';
        }
    }
}
