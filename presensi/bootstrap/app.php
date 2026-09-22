<?php
/**
 * Bootstrap aplikasi: autoload, helper, konfigurasi, error handler.
 * Tidak memerlukan Composer.
 */

declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
define('APP_START', microtime(true));

if (PHP_VERSION_ID < 80000) {
    http_response_code(500);
    exit('Aplikasi ini membutuhkan PHP 8.0 atau lebih baru. Versi server: ' . PHP_VERSION
        . '. Ubah versi PHP di panel hosting (Select PHP Version / PHP Selector).');
}

// Autoloader PSR-4 sederhana: App\Foo\Bar => app/Foo/Bar.php
spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'App\\', 4) !== 0) {
        return;
    }
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require BASE_PATH . '/app/Core/helpers.php';

App\Core\Config::load();

date_default_timezone_set((string) config('app.timezone', 'Asia/Jakarta'));
mb_internal_encoding('UTF-8');

// Jangan pernah tampilkan error mentah ke pengunjung di mode produksi.
error_reporting(E_ALL);
ini_set('display_errors', config('app.debug') ? '1' : '0');
ini_set('log_errors', '1');
$logDir = BASE_PATH . '/storage/logs';
if (is_dir($logDir) && is_writable($logDir)) {
    ini_set('error_log', $logDir . '/php-error.log');
}

App\Core\ErrorHandler::register();

return new App\Core\App();
