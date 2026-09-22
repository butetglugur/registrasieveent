<!doctype html>
<html lang="id">
<head>
<?= $this->partial('partials.head', ['title' => 'Instalasi']) ?>
<meta name="robots" content="noindex">
</head>
<body class="public">
<div class="bg-blobs" aria-hidden="true"><span></span><span></span><span></span></div>
<?= $this->partial('partials.flash') ?>
<div class="install-wrap">
  <div class="hero" style="padding-top:1rem">
    <span class="eyebrow"><?= icon('zap') ?> Instalasi 1 menit</span>
    <h1>Pasang <span class="grad-text">Presensi Event</span></h1>
    <p>Buat database MySQL di panel hosting (DirectAdmin / cPanel), lalu isi formulir di bawah.</p>
  </div>

  <?php if (!empty($manualEnv)): ?>
    <div class="card mb-2"><div class="card-body">
      <h3><?= icon('alert') ?> Buat file <code>config/env.php</code> secara manual</h3>
      <p class="muted">Salin isi berikut ke file <code>config/env.php</code> lewat File Manager, lalu buat file kosong <code>storage/installed.lock</code>.</p>
      <pre class="code"><?= e($manualEnv) ?></pre>
    </div></div>
  <?php endif; ?>

  <div class="card mb-2">
    <div class="card-header"><h3><?= icon('list-checks') ?> Pemeriksaan server</h3></div>
    <div class="card-body req-list">
      <?php foreach ($requirements as [$label, $ok]): ?>
        <div class="<?= $ok ? 'text-success' : 'text-danger' ?>"><?= icon($ok ? 'check-circle' : 'x-circle') ?><span><?= e($label) ?></span></div>
      <?php endforeach; ?>
    </div>
  </div>

  <form method="post" action="<?= e(route('install.store')) ?>" class="card" data-loading-form>
    <?= csrf_field() ?>
    <div class="card-header"><h3><?= icon('database') ?> Koneksi database</h3></div>
    <div class="card-body">
      <div class="form-grid cols-2">
        <div class="form-group"><label class="label" for="db_host">Host</label><input id="db_host" class="input" name="db_host" value="<?= e(old('db_host', 'localhost')) ?>" required><div class="hint">Biasanya <code>localhost</code>.</div><?= $this->partial('partials.field-error', ['field' => 'db_host']) ?></div>
        <div class="form-group"><label class="label" for="db_port">Port</label><input id="db_port" class="input" name="db_port" value="<?= e(old('db_port', '3306')) ?>" inputmode="numeric"><?= $this->partial('partials.field-error', ['field' => 'db_port']) ?></div>
        <div class="form-group"><label class="label" for="db_name">Nama database</label><input id="db_name" class="input" name="db_name" value="<?= e(old('db_name')) ?>" required placeholder="mis. user_presensi"><?= $this->partial('partials.field-error', ['field' => 'db_name']) ?></div>
        <div class="form-group"><label class="label" for="db_user">User database</label><input id="db_user" class="input" name="db_user" value="<?= e(old('db_user')) ?>" required autocomplete="off"><?= $this->partial('partials.field-error', ['field' => 'db_user']) ?></div>
        <div class="form-group span-2"><label class="label" for="db_pass">Password database</label><input id="db_pass" class="input" type="password" name="db_pass" autocomplete="new-password"></div>
      </div>
      <label class="switch"><input type="checkbox" name="import_legacy" value="1" checked> Impor data dari aplikasi versi lama (tabel <code>attendees</code>, <code>settings</code>, <code>admins</code>) bila ada</label>
    </div>
    <div class="card-header" style="border-top:1px solid var(--border)"><h3><?= icon('settings') ?> Aplikasi & akun admin</h3></div>
    <div class="card-body">
      <div class="form-grid cols-2">
        <div class="form-group"><label class="label" for="app_name">Nama aplikasi</label><input id="app_name" class="input" name="app_name" value="<?= e(old('app_name', 'Presensi Event')) ?>" required maxlength="60"><?= $this->partial('partials.field-error', ['field' => 'app_name']) ?></div>
        <div class="form-group"><label class="label" for="app_url">URL aplikasi</label><input id="app_url" class="input" name="app_url" value="<?= e(old('app_url', $detectedUrl)) ?>" placeholder="https://presensi.domain.com"><div class="hint">Untuk link QR tiket. Gunakan https bila SSL aktif.</div><?= $this->partial('partials.field-error', ['field' => 'app_url']) ?></div>
        <div class="form-group"><label class="label" for="admin_name">Nama admin</label><input id="admin_name" class="input" name="admin_name" value="<?= e(old('admin_name', 'Administrator')) ?>" required><?= $this->partial('partials.field-error', ['field' => 'admin_name']) ?></div>
        <div class="form-group"><label class="label" for="admin_username">Username admin</label><input id="admin_username" class="input" name="admin_username" value="<?= e(old('admin_username', 'admin')) ?>" required autocomplete="off"><?= $this->partial('partials.field-error', ['field' => 'admin_username']) ?></div>
        <div class="form-group"><label class="label" for="admin_password">Password admin</label><input id="admin_password" class="input" type="password" name="admin_password" required autocomplete="new-password"><div class="hint">Minimal 8 karakter, kombinasi huruf & angka.</div><?= $this->partial('partials.field-error', ['field' => 'admin_password']) ?></div>
        <div class="form-group"><label class="label" for="admin_password_confirmation">Ulangi password</label><input id="admin_password_confirmation" class="input" type="password" name="admin_password_confirmation" required autocomplete="new-password"></div>
      </div>
    </div>
    <div class="card-footer flex justify-between items-center wrap gap-1">
      <span class="muted small"><?= icon('lock') ?> Installer terkunci otomatis setelah selesai.</span>
      <button class="btn btn-primary btn-lg" type="submit"><span class="spinner"></span><?= icon('zap') ?> Instal Sekarang</button>
    </div>
  </form>
</div>
</body>
</html>
