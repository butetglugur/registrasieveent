<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    public int $status = 200;
    public string $body = '';
    /** @var array<string,string> */
    public array $headers = [];
    /** @var callable|null */
    private $stream = null;

    public function __construct(string $body = '', int $status = 200, array $headers = [])
    {
        $this->body = $body;
        $this->status = $status;
        $this->headers = $headers;
    }

    public static function redirect(string $to, int $status = 302): Response
    {
        // Cegah header injection
        $to = str_replace(["\r", "\n"], '', $to);
        return new Response('', $status, ['Location' => $to]);
    }

    public static function json($data, int $status = 200): Response
    {
        $body = (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return new Response($body, $status, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    public static function stream(callable $callback, array $headers): Response
    {
        $r = new Response('', 200, $headers);
        $r->stream = $callback;
        return $r;
    }

    /** Flash pesan untuk halaman tujuan redirect. */
    public function with(string $type, string $message): Response
    {
        Session::instance()->flash($type, $message);
        return $this;
    }

    /** Simpan error validasi + input lama. */
    public function withErrors(array $errors, array $old = []): Response
    {
        $s = Session::instance();
        $s->flash('_errors', $errors);
        unset($old['_token']);
        foreach (array_keys($old) as $k) {
            if (stripos((string) $k, 'pass') !== false) {
                unset($old[$k]); // jangan pernah simpan password di sesi
            }
        }
        $s->flash('_old', $old);
        return $this;
    }

    public function header(string $name, string $value): Response
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            if ($this->status === 419) {
                // Apache tidak mengenal 419 dan akan mengubahnya jadi 500 bila tanpa reason phrase.
                $proto = (string) ($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1');
                header($proto . ' 419 Page Expired', true, 419);
            } else {
                http_response_code($this->status);
            }
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }
        if ($this->stream !== null) {
            ($this->stream)();
            return;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
            echo $this->body;
        }
    }

    public function isStream(): bool
    {
        return $this->stream !== null;
    }
}
