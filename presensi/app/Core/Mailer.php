<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Pengirim email tanpa library: SMTP (STARTTLS/SSL + AUTH LOGIN) atau fungsi mail() PHP.
 * ⚠️ Mengirim data peserta ke server email yang dikonfigurasi admin.
 */
final class Mailer
{
    /** @var array<string,mixed> */
    private array $cfg;
    /** @var resource|null */
    private $sock = null;

    /**
     * @param array{driver:string,host?:string,port?:int,encryption?:string,username?:string,password?:string,from:string,from_name?:string} $cfg
     */
    public function __construct(array $cfg)
    {
        $this->cfg = $cfg;
    }

    /** Kirim email multipart (teks + HTML). Lempar exception bila gagal. */
    public function send(string $to, string $subject, string $text, string $html): void
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Alamat email penerima tidak valid');
        }
        $from = (string) $this->cfg['from'];
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Alamat email pengirim belum diatur');
        }
        $boundary = 'b' . bin2hex(random_bytes(12));
        $fromName = self::encodeHeader((string) ($this->cfg['from_name'] ?? ''));
        $headers = [
            'From: ' . ($fromName !== '' ? $fromName . ' ' : '') . '<' . $from . '>',
            'Reply-To: ' . $from,
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'X-Mailer: PresensiEvent',
        ];
        $body = '--' . $boundary . "\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($text))
            . '--' . $boundary . "\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html))
            . '--' . $boundary . "--\r\n";
        $encSubject = self::encodeHeader($subject);

        if (($this->cfg['driver'] ?? 'smtp') === 'mail') {
            if (!@mail($to, $encSubject, $body, implode("\r\n", $headers), '-f' . $from)) {
                throw new \RuntimeException('Fungsi mail() server gagal mengirim');
            }
            return;
        }

        $data = 'To: <' . $to . ">\r\nSubject: " . $encSubject . "\r\nDate: " . date('r') . "\r\n"
            . 'Message-ID: <' . bin2hex(random_bytes(8)) . '@' . (explode('@', $from)[1] ?? 'localhost') . ">\r\n"
            . implode("\r\n", $headers) . "\r\n\r\n" . $body;
        $this->smtp($from, $to, $data);
    }

    private function smtp(string $from, string $to, string $data): void
    {
        $host = (string) ($this->cfg['host'] ?? '');
        $port = (int) ($this->cfg['port'] ?? 587);
        $enc = (string) ($this->cfg['encryption'] ?? 'tls');
        if ($host === '') {
            throw new \RuntimeException('Host SMTP belum diatur');
        }
        $remote = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
        $errno = 0;
        $errstr = '';
        $sock = @stream_socket_client($remote, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
        if (!$sock) {
            throw new \RuntimeException('Tidak dapat terhubung ke SMTP ' . $host . ':' . $port . ' (' . $errstr . ')');
        }
        $this->sock = $sock;
        stream_set_timeout($sock, 15);
        try {
            $this->expect(220);
            $ehloHost = preg_replace('/[^a-z0-9.\-]/i', '', (string) ($_SERVER['SERVER_NAME'] ?? 'localhost')) ?: 'localhost';
            $this->cmd('EHLO ' . $ehloHost, 250);
            if ($enc === 'tls') {
                $this->cmd('STARTTLS', 220);
                if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                    throw new \RuntimeException('STARTTLS gagal');
                }
                $this->cmd('EHLO ' . $ehloHost, 250);
            }
            $user = (string) ($this->cfg['username'] ?? '');
            if ($user !== '') {
                $this->cmd('AUTH LOGIN', 334);
                $this->cmd(base64_encode($user), 334);
                $this->cmd(base64_encode((string) ($this->cfg['password'] ?? '')), 235, true);
            }
            $this->cmd('MAIL FROM:<' . $from . '>', 250);
            $this->cmd('RCPT TO:<' . $to . '>', [250, 251]);
            $this->cmd('DATA', 354);
            // Dot-stuffing sesuai RFC 5321
            $data = preg_replace('/^\./m', '..', str_replace(["\r\n", "\n"], ["\n", "\r\n"], $data)) ?? $data;
            $this->cmd($data . "\r\n.", 250);
            @fwrite($sock, "QUIT\r\n");
        } finally {
            @fclose($sock);
            $this->sock = null;
        }
    }

    /** @param int|int[] $expect */
    private function cmd(string $line, $expect, bool $secret = false): string
    {
        if (@fwrite($this->sock, $line . "\r\n") === false) {
            throw new \RuntimeException('Koneksi SMTP terputus');
        }
        return $this->expect($expect, $secret ? '[rahasia]' : strtok($line, "\r\n"));
    }

    /** @param int|int[] $expect */
    private function expect($expect, string $after = ''): string
    {
        $resp = '';
        while (($line = fgets($this->sock, 515)) !== false) {
            $resp .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        $code = (int) substr($resp, 0, 3);
        if (!in_array($code, (array) $expect, true)) {
            $where = $after !== '' ? ' setelah ' . (str_starts_with($after, 'AUTH') || $after === '[rahasia]' ? 'autentikasi' : strtok($after, ' ')) : '';
            throw new \RuntimeException('SMTP menolak' . $where . ': ' . trim(mb_substr($resp, 0, 150)));
        }
        return $resp;
    }

    public static function encodeHeader(string $s): string
    {
        $s = str_replace(["\r", "\n"], '', $s);
        return preg_match('/[^\x20-\x7E]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
    }
}
