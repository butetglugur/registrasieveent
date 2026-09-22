<?php
$this->extend('layouts.admin');
$title = 'Akun Saya';
$crumb = '@' . $user['username'];
?>
<form method="post" action="<?= e(route('admin.account.update')) ?>" class="card" style="max-width:640px" data-loading-form>
  <?= csrf_field() ?>
  <div class="card-header"><h3><?= icon('user') ?> Profil</h3></div>
  <div class="card-body">
    <div class="form-group"><label class="label" for="name">Nama</label><input id="name" class="input <?= error('name') ? 'is-invalid' : '' ?>" name="name" value="<?= e(old('name', $user['name'])) ?>" required maxlength="100"><?= $this->partial('partials.field-error', ['field' => 'name']) ?></div>
    <div class="form-group"><label class="label" for="email">Email</label><input id="email" class="input <?= error('email') ? 'is-invalid' : '' ?>" name="email" value="<?= e(old('email', (string) $user['email'])) ?>" maxlength="150"><?= $this->partial('partials.field-error', ['field' => 'email']) ?></div>
    <div class="form-section-title mt-2">Ganti password <span class="muted" style="text-transform:none;letter-spacing:0;font-weight:500">(kosongkan jika tidak diganti)</span></div>
    <div class="form-group"><label class="label" for="current_password">Password saat ini</label><input id="current_password" class="input <?= error('current_password') ? 'is-invalid' : '' ?>" type="password" name="current_password" autocomplete="current-password"><?= $this->partial('partials.field-error', ['field' => 'current_password']) ?></div>
    <div class="form-grid cols-2">
      <div class="form-group"><label class="label" for="password">Password baru</label><input id="password" class="input <?= error('password') ? 'is-invalid' : '' ?>" type="password" name="password" autocomplete="new-password"><?= $this->partial('partials.field-error', ['field' => 'password']) ?></div>
      <div class="form-group"><label class="label" for="password_confirmation">Ulangi password baru</label><input id="password_confirmation" class="input" type="password" name="password_confirmation" autocomplete="new-password"></div>
    </div>
    <p class="muted small mb-0"><?= icon('info') ?> Login terakhir: <?= e($user['last_login_at'] ? date_id($user['last_login_at']) . ' dari ' . $user['last_login_ip'] : '-') ?></p>
  </div>
  <div class="card-footer flex" style="justify-content:flex-end"><button class="btn btn-primary" type="submit"><span class="spinner"></span><?= icon('save') ?> Simpan</button></div>
</form>
