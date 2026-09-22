<?php
/**
 * Front controller. Semua request diarahkan ke sini oleh .htaccess.
 */

define('BASE_PATH', dirname(__DIR__));

$app = require BASE_PATH . '/bootstrap/app.php';
$app->run();
