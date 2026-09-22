<?php
$titles = [403 => 'Akses ditolak', 404 => 'Halaman tidak ditemukan', 405 => 'Metode tidak diizinkan',
           419 => 'Sesi kedaluwarsa', 429 => 'Terlalu banyak permintaan', 500 => 'Terjadi kesalahan', 503 => 'Sedang pemeliharaan'];
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($titles[$status] ?? 'Error') ?></title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="public">
<div class="bg-blobs" aria-hidden="true"><span></span><span></span><span></span></div>
<div class="error-page">
  <div>
    <div class="error-code grad-text"><?= (int) $status ?></div>
    <h2><?= e($titles[$status] ?? 'Terjadi kesalahan') ?></h2>
    <p class="text-2" style="max-width:480px;margin-inline:auto"><?= e($message) ?></p>
    <div class="flex gap-1" style="justify-content:center">
      <a class="btn btn-primary" href="<?= e(url('/')) ?>"><?= icon('home') ?> Beranda</a>
    </div>
  </div>
</div>
</body>
</html>
