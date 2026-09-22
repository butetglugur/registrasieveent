<?php
/**
 * Mini test framework (tanpa PHPUnit/Composer).
 */

declare(strict_types=1);

final class T
{
    public static int $pass = 0;
    public static int $fail = 0;
    public static array $failures = [];
    public static string $group = '';

    public static function group(string $name): void
    {
        self::$group = $name;
        echo "\n\033[1;35m▸ {$name}\033[0m\n";
    }

    public static function ok(bool $cond, string $msg, string $detail = ''): void
    {
        if ($cond) {
            self::$pass++;
            echo "  \033[32m✔\033[0m {$msg}\n";
        } else {
            self::$fail++;
            self::$failures[] = '[' . self::$group . '] ' . $msg . ($detail !== '' ? "\n      " . $detail : '');
            echo "  \033[31m✘ {$msg}\033[0m" . ($detail !== '' ? "\n      " . substr($detail, 0, 600) : '') . "\n";
        }
    }

    public static function eq($expected, $actual, string $msg): void
    {
        self::ok($expected === $actual, $msg, 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }

    public static function contains(string $needle, string $haystack, string $msg): void
    {
        self::ok(str_contains($haystack, $needle), $msg, 'tidak ditemukan: ' . $needle);
    }

    public static function notContains(string $needle, string $haystack, string $msg): void
    {
        self::ok(!str_contains($haystack, $needle), $msg, 'seharusnya tidak ada: ' . $needle);
    }

    public static function summary(): int
    {
        $total = self::$pass + self::$fail;
        echo "\n" . str_repeat('─', 60) . "\n";
        if (self::$fail === 0) {
            echo "\033[1;32m✔ SEMUA LULUS: {$total} assertion\033[0m\n";
        } else {
            echo "\033[1;31m✘ GAGAL: " . self::$fail . " dari {$total}\033[0m\n";
            foreach (self::$failures as $f) {
                echo "  - {$f}\n";
            }
        }
        return self::$fail === 0 ? 0 : 1;
    }
}

/** HTTP client sederhana dengan cookie jar. */
final class Http
{
    public string $base;
    public array $cookies = [];
    public array $lastHeaders = [];
    public int $status = 0;
    public string $body = '';

    public function __construct(string $base)
    {
        $this->base = rtrim($base, '/');
    }

    public function request(string $method, string $path, array $data = [], array $headers = []): Http
    {
        $url = str_starts_with($path, 'http') ? $path : $this->base . $path;
        $ch = curl_init();
        $h = $headers;
        if ($this->cookies) {
            $pairs = [];
            foreach ($this->cookies as $k => $v) {
                $pairs[] = $k . '=' . $v;
            }
            $h[] = 'Cookie: ' . implode('; ', $pairs);
        }
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $h,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_NOPROXY => '*',
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
        $raw = (string) curl_exec($ch);
        $this->status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $headerText = substr($raw, 0, $hs);
        $this->body = substr($raw, $hs);
        $this->lastHeaders = [];
        foreach (explode("\r\n", $headerText) as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $k = strtolower(trim($k));
                $v = trim($v);
                if ($k === 'set-cookie') {
                    $c = explode(';', $v)[0];
                    [$ck, $cv] = array_pad(explode('=', $c, 2), 2, '');
                    if ($cv === '' || stripos($v, 'expires=Thu, 01 Jan 1970') !== false || preg_match('/Max-Age=0/i', $v)) {
                        unset($this->cookies[$ck]);
                    } else {
                        $this->cookies[$ck] = $cv;
                    }
                }
                $this->lastHeaders[$k] = $v;
            }
        }
        return $this;
    }

    public function get(string $path, array $headers = []): Http
    {
        return $this->request('GET', $path, [], $headers);
    }

    public function post(string $path, array $data = [], array $headers = []): Http
    {
        return $this->request('POST', $path, $data, $headers);
    }

    /** Ikuti redirect (GET) hingga maksimal N kali. */
    public function follow(int $max = 5): Http
    {
        while ($max-- > 0 && in_array($this->status, [301, 302, 303], true)) {
            $loc = $this->header('location');
            if (str_starts_with($loc, '/')) {
                // path absolut: gabungkan dengan skema+host (bukan base yang mungkin berisi subfolder)
                $parts = parse_url($this->base);
                $loc = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '') . $loc;
            }
            $this->get($loc);
        }
        return $this;
    }

    public function header(string $name): string
    {
        return $this->lastHeaders[strtolower($name)] ?? '';
    }

    public function location(): string
    {
        $l = $this->header('location');
        return preg_replace('#^https?://[^/]+#', '', $l) ?? $l;
    }

    /** Ambil CSRF token dari halaman terakhir. */
    public function csrf(): string
    {
        if (preg_match('/name="_token" value="([a-f0-9]+)"/', $this->body, $m)) {
            return $m[1];
        }
        if (preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $this->body, $m)) {
            return $m[1];
        }
        return '';
    }

    public function field(string $name): string
    {
        if (preg_match('/name="' . preg_quote($name, '/') . '" value="([^"]*)"/', $this->body, $m)) {
            return html_entity_decode($m[1], ENT_QUOTES);
        }
        return '';
    }

    public function json(): array
    {
        $d = json_decode($this->body, true);
        return is_array($d) ? $d : [];
    }
}
