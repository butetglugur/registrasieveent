<?php

declare(strict_types=1);

namespace App\Core;

/**
 * HTTP client minimal (cURL, fallback stream) untuk memanggil API eksternal.
 * ⚠️ Dipakai untuk mengirim data ke layanan pihak ketiga (WA gateway / webhook).
 */
final class HttpClient
{
    /**
     * @param array<string,string> $headers
     * @param array|string $body array = form-urlencoded, string = raw (mis. JSON)
     * @return array{status:int,body:string,error:string}
     */
    public static function post(string $url, $body, array $headers = [], int $timeout = 10): array
    {
        $payload = is_array($body) ? http_build_query($body) : $body;
        if (is_array($body) && !isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        }
        $headers['User-Agent'] = $headers['User-Agent'] ?? 'PresensiEvent/' . config('app.version');
        $lines = [];
        foreach ($headers as $k => $v) {
            $lines[] = $k . ': ' . str_replace(["\r", "\n"], '', (string) $v);
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => $lines,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $res = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = $res === false ? curl_error($ch) : '';
            curl_close($ch);
            return ['status' => $status, 'body' => is_string($res) ? $res : '', 'error' => $err];
        }

        $ctx = stream_context_create(['http' => [
            'method' => 'POST', 'header' => implode("\r\n", $lines), 'content' => $payload,
            'timeout' => $timeout, 'ignore_errors' => true, 'follow_location' => 0,
        ]]);
        $res = @file_get_contents($url, false, $ctx);
        $status = 0;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
                $status = (int) $m[1];
            }
        }
        return ['status' => $status, 'body' => is_string($res) ? $res : '', 'error' => $res === false ? 'Koneksi gagal' : ''];
    }
}
