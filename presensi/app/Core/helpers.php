<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Config;
use App\Core\HttpException;
use App\Core\Icons;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Models\Setting;

if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
    }
}

/** @return mixed */
function config(string $key, $default = null)
{
    return Config::get($key, $default);
}

/** Escape HTML — WAJIB dipakai untuk semua data dinamis di view. */
function e($value): string
{
    if ($value === null || $value === false) {
        return '';
    }
    if (is_array($value)) {
        $value = implode(', ', array_map('strval', $value));
    }
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_path(string $path = ''): string
{
    return BASE_PATH . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function storage_path(string $path = ''): string
{
    return base_path('storage' . ($path !== '' ? '/' . ltrim($path, '/') : ''));
}

function request(): Request
{
    return Request::current();
}

/** URL relatif terhadap root aplikasi (mendukung instalasi di subfolder). */
function url(string $path = '', array $query = []): string
{
    $base = Request::basePath();
    $path = '/' . ltrim($path, '/');
    $u = $base . ($path === '/' ? '/' : $path);
    $query = array_filter($query, static fn($v) => $v !== null && $v !== '');
    if ($query) {
        $u .= '?' . http_build_query($query);
    }
    return $u;
}

/** URL absolut (dengan skema & host) untuk dibagikan / QR code. */
function full_url(string $path = '', array $query = []): string
{
    $configured = rtrim((string) config('app.url', ''), '/');
    if ($configured !== '') {
        $rel = url($path, $query);
        $base = Request::basePath();
        if ($base !== '' && str_starts_with($rel, $base)) {
            $rel = substr($rel, strlen($base));
        }
        return $configured . $rel;
    }
    return Request::current()->origin() . url($path, $query);
}

function asset(string $path): string
{
    $file = base_path('public/assets/' . ltrim($path, '/'));
    $v = is_file($file) ? (string) filemtime($file) : config('app.version');
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}

function route(string $name, array $params = [], array $query = []): string
{
    return url(Router::instance()->pathFor($name, $params), $query);
}

function redirect(string $to): Response
{
    return Response::redirect($to);
}

/** Kembali ke halaman sebelumnya (hanya jika masih di host yang sama). */
function back(string $fallback = ''): Response
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    if ($ref !== '') {
        $host = parse_url($ref, PHP_URL_HOST);
        if ($host !== null && strcasecmp((string) $host, Request::current()->host()) === 0) {
            return Response::redirect($ref);
        }
    }
    return Response::redirect($fallback !== '' ? $fallback : url('/'));
}

function view(string $name, array $data = []): string
{
    return View::make($name, $data);
}

function abort(int $code, string $message = ''): void
{
    throw new HttpException($code, $message);
}

function session(): Session
{
    return Session::instance();
}

function flash(string $type, string $message): void
{
    Session::instance()->flash($type, $message);
}

/** Nilai input lama setelah validasi gagal. */
function old(string $key, $default = '')
{
    $old = Session::instance()->getFlash('_old', []);
    if (is_array($old) && array_key_exists($key, $old)) {
        return $old[$key];
    }
    return $default;
}

/** Pesan error validasi pertama untuk sebuah field. */
function error(string $field): ?string
{
    $errors = Session::instance()->getFlash('_errors', []);
    if (is_array($errors) && !empty($errors[$field])) {
        return is_array($errors[$field]) ? (string) reset($errors[$field]) : (string) $errors[$field];
    }
    return null;
}

function errors(): array
{
    $errors = Session::instance()->getFlash('_errors', []);
    return is_array($errors) ? $errors : [];
}

function csrf_token(): string
{
    return Session::instance()->csrfToken();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

/** @return array<string,mixed>|null */
function auth_user(): ?array
{
    return Auth::user();
}

function is_admin(): bool
{
    $u = Auth::user();
    return $u !== null && ($u['role'] ?? '') === 'admin';
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function csp_nonce(): string
{
    static $nonce = null;
    if ($nonce === null) {
        $nonce = base64_encode(random_bytes(16));
    }
    return $nonce;
}

function setting(string $key, $default = null)
{
    return Setting::get($key, $default);
}

function icon(string $name, string $class = 'icon'): string
{
    return Icons::svg($name, $class);
}

function app_name(): string
{
    $name = '';
    try {
        $name = (string) setting('app_name', '');
    } catch (Throwable $e) {
        $name = '';
    }
    return $name !== '' ? $name : (string) config('app.name');
}

/**
 * Normalisasi nomor WhatsApp ke format internasional tanpa "+".
 * 0812-3456-789 / +62 812 3456 789 / 812345678 => 628123456789
 */
function normalize_wa(string $raw, string $countryCode = '62'): string
{
    $digits = preg_replace('/\D+/', '', $raw) ?? '';
    if ($digits === '') {
        return '';
    }
    if (str_starts_with($digits, '00')) {
        $digits = substr($digits, 2);
    } elseif (str_starts_with($digits, '0')) {
        $digits = $countryCode . substr($digits, 1);
    } elseif ($countryCode === '62' && str_starts_with($digits, '8')) {
        $digits = '62' . $digits;
    }
    return $digits;
}

function valid_wa(string $normalized): bool
{
    return (bool) preg_match('/^[1-9]\d{8,14}$/', $normalized);
}

function mask_wa(string $wa): string
{
    $len = strlen($wa);
    if ($len <= 6) {
        return $wa;
    }
    return substr($wa, 0, 4) . str_repeat('•', max(0, $len - 7)) . substr($wa, -3);
}

function wa_link(string $wa, string $text = ''): string
{
    return 'https://wa.me/' . rawurlencode($wa) . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}

function slugify(string $text, int $max = 60): string
{
    if (function_exists('transliterator_transliterate')) {
        $t = @transliterator_transliterate('Any-Latin; Latin-ASCII', $text);
        if (is_string($t)) {
            $text = $t;
        }
    }
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    $text = trim($text, '-');
    if (strlen($text) > $max) {
        $text = rtrim(substr($text, 0, $max), '-');
    }
    return $text;
}

function str_limit(string $text, int $limit = 80, string $end = '…'): string
{
    return mb_strlen($text) > $limit ? rtrim(mb_substr($text, 0, $limit)) . $end : $text;
}

/** Cegah CSV/Formula injection saat file dibuka di Excel. */
function csv_safe($value): string
{
    $value = (string) $value;
    if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
        return "'" . $value;
    }
    return $value;
}

/** Hanya izinkan URL http(s) — mencegah javascript:, data:, dll. */
function safe_url(?string $url): string
{
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return '';
    }
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true) ? $url : '';
}

const ID_MONTHS = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const ID_MONTHS_SHORT = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const ID_DAYS = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

function date_id(?string $datetime, bool $withTime = true, bool $short = false): string
{
    if (!$datetime) {
        return '-';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return '-';
    }
    $months = $short ? ID_MONTHS_SHORT : ID_MONTHS;
    $out = date('j', $ts) . ' ' . $months[(int) date('n', $ts)] . ' ' . date('Y', $ts);
    if ($withTime) {
        $out .= ', ' . date('H:i', $ts);
    }
    return $out;
}

function day_id(?string $datetime): string
{
    $ts = $datetime ? strtotime($datetime) : false;
    return $ts ? ID_DAYS[(int) date('w', $ts)] : '';
}

function time_ago(?string $datetime): string
{
    if (!$datetime) {
        return '-';
    }
    $diff = time() - (int) strtotime($datetime);
    if ($diff < 60) {
        return 'baru saja';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . ' menit lalu';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . ' jam lalu';
    }
    if ($diff < 86400 * 7) {
        return floor($diff / 86400) . ' hari lalu';
    }
    return date_id($datetime, false, true);
}

function number_id($n): string
{
    return number_format((float) $n, 0, ',', '.');
}

/** Tema warna event (gradient). */
function themes(): array
{
    return [
        'violet'  => ['label' => 'Violet Dream',  'from' => '#7c3aed', 'via' => '#c026d3', 'to' => '#f472b6'],
        'ocean'   => ['label' => 'Ocean Breeze',  'from' => '#0ea5e9', 'via' => '#2563eb', 'to' => '#7c3aed'],
        'sunset'  => ['label' => 'Sunset Glow',   'from' => '#f97316', 'via' => '#ef4444', 'to' => '#db2777'],
        'emerald' => ['label' => 'Emerald Fresh', 'from' => '#10b981', 'via' => '#14b8a6', 'to' => '#0ea5e9'],
        'mango'   => ['label' => 'Mango Tango',   'from' => '#f59e0b', 'via' => '#f97316', 'to' => '#e11d48'],
        'aurora'  => ['label' => 'Aurora',        'from' => '#22d3ee', 'via' => '#a855f7', 'to' => '#ec4899'],
        'forest'  => ['label' => 'Deep Forest',   'from' => '#15803d', 'via' => '#0d9488', 'to' => '#1e40af'],
        'midnight' => ['label' => 'Midnight',     'from' => '#1e293b', 'via' => '#4338ca', 'to' => '#7e22ce'],
    ];
}

function theme_style(?string $key): string
{
    $all = themes();
    $t = $all[$key ?? ''] ?? $all['violet'];
    return '--g1:' . $t['from'] . ';--g2:' . $t['via'] . ';--g3:' . $t['to'] . ';';
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function random_code(int $length = 8): string
{
    // Tanpa karakter ambigu (0/O, 1/I/L)
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $max = strlen($alphabet) - 1;
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $alphabet[random_int(0, $max)];
    }
    return $out;
}

function is_installed(): bool
{
    return Config::hasEnv() && is_file(storage_path('installed.lock'));
}
