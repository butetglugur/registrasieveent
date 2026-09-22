<?php
/**
 * Fallback bila aplikasi diunggah langsung ke public_html dan
 * mod_rewrite tidak memproses URL root. Normalnya .htaccess sudah
 * mengarahkan semua request ke folder public/.
 */

define('BASE_PATH', __DIR__);

$app = require BASE_PATH . '/bootstrap/app.php';
$app->run();
