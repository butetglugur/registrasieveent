<?php

use App\Core\Config;

return [
    'name'     => 'Presensi Event',
    'version'  => '2.0.0',
    'url'      => (string) Config::env('APP_URL', ''),
    'key'      => (string) Config::env('APP_KEY', ''),
    'debug'    => (bool) Config::env('APP_DEBUG', false),
    'timezone' => (string) Config::env('APP_TIMEZONE', 'Asia/Jakarta'),

    // Sesi login admin
    'session_name'     => 'presensi_session',
    'session_lifetime' => 120,   // menit tanpa aktivitas sebelum logout otomatis

    // Proteksi brute-force login
    'login_max_attempts' => 5,   // per username + IP
    'login_decay'        => 900, // detik (15 menit)
];
