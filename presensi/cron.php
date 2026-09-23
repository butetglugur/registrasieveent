<?php
/**
 * Cron antrean notifikasi (CLI). Jalankan tiap 1 menit dari panel hosting:
 *   DirectAdmin: Advanced Features > Cron Jobs  |  cPanel: Cron Jobs
 *   Perintah  :  php /home/USER/domains/DOMAIN/public_html/cron.php
 *   Jadwal    :  * * * * *   (setiap menit)
 * Skrip berjalan ±55 detik sambil mematuhi jeda anti-blokir WhatsApp.
 * File ini tidak bisa dibuka dari browser (diblokir .htaccess & cek PHP_SAPI).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('BASE_PATH', __DIR__);
$_SERVER['REQUEST_URI'] = '/cron-cli';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';

require BASE_PATH . '/bootstrap/app.php';

if (!is_installed()) {
    fwrite(STDERR, "Aplikasi belum diinstal.\n");
    exit(1);
}

App\Core\Migrator::ensure();
if (!App\Services\Notifier::enabled()) {
    echo "Notifikasi nonaktif.\n";
    exit(0);
}

$seconds = (int) ($argv[1] ?? 55);
$r = App\Services\Notifier::runFor(max(5, min(300, $seconds)));
echo date('Y-m-d H:i:s') . " terkirim={$r['sent']} gagal={$r['failed']}\n";
