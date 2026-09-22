<!doctype html>
<html lang="id">
<head>
<?php $title = 'Login Admin'; ?>
<?= $this->partial('partials.head', ['title' => $title]) ?>
<meta name="robots" content="noindex">
</head>
<body>
<?= $this->partial('partials.flash') ?>
<div class="auth-page">
  <section class="auth-art">
    <a class="brand" href="<?= e(url('/')) ?>"><span class="brand-mark" style="background:rgba(255,255,255,.2)"><?= icon('sparkles') ?></span><?= e(app_name()) ?></a>
    <div>
      <h2>Kelola pendaftaran & kehadiran event dalam satu dasbor.</h2>
      <div class="features">
        <div><?= icon('ticket') ?> E-tiket QR otomatis untuk setiap peserta</div>
        <div><?= icon('scan') ?> Check-in cepat dengan kamera HP</div>
        <div><?= icon('file') ?> Export Excel sekali klik</div>
        <div><?= icon('shield') ?> Aman: CSRF, rate-limit, password terenkripsi</div>
      </div>
    </div>
    <small style="opacity:.8">© <?= date('Y') ?> <?= e(setting('org_name', '') ?: app_name()) ?></small>
  </section>
  <section class="auth-form">
    <div class="box">
      <div class="brand mb-2" style="justify-content:center"><span class="brand-mark"><?= icon('lock') ?></span></div>
      <h2 class="text-center">Masuk ke Admin</h2>
      <p class="muted text-center mb-3">Gunakan akun yang diberikan administrator.</p>
      <form method="post" action="<?= e(route('login.attempt')) ?>" data-loading-form>
        <?= csrf_field() ?>
        <div class="form-group">
          <label class="label" for="username">Username</label>
          <div class="input-icon"><?= icon('user') ?><input id="username" class="input" name="username" value="<?= e(old('username')) ?>" required autocomplete="username" autofocus maxlength="60"></div>
        </div>
        <div class="form-group">
          <label class="label" for="password">Password</label>
          <div class="input-icon"><?= icon('key') ?><input id="password" class="input" type="password" name="password" required autocomplete="current-password" data-password></div>
          <label class="switch mt-1" style="font-weight:500;font-size:.82rem"><input type="checkbox" data-toggle-password="#password"> Tampilkan password</label>
        </div>
        <button class="btn btn-primary btn-lg btn-block" type="submit"><span class="spinner"></span><?= icon('arrow-right') ?> Masuk</button>
      </form>
      <p class="text-center mt-3"><a href="<?= e(url('/')) ?>"><?= icon('arrow-left') ?> Kembali ke halaman publik</a></p>
    </div>
  </section>
</div>
</body>
</html>
