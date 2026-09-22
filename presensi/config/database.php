<?php

use App\Core\Config;

return [
    'host'     => (string) Config::env('DB_HOST', 'localhost'),
    'port'     => (int) Config::env('DB_PORT', 3306),
    'database' => (string) Config::env('DB_DATABASE', ''),
    'username' => (string) Config::env('DB_USERNAME', ''),
    'password' => (string) Config::env('DB_PASSWORD', ''),
    'charset'  => 'utf8mb4',
    'prefix'   => '',
];
