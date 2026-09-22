<?php
// Router untuk `php -S` yang meniru .htaccess (hanya untuk pengujian lokal).
$root = realpath(__DIR__ . '/../presensi/public');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = realpath($root . $path);
if ($path !== '/' && $file && str_starts_with($file, $root) && is_file($file) && !str_ends_with($file, '.php')) {
    return false; // file statis
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
chdir($root);
require $root . '/index.php';
