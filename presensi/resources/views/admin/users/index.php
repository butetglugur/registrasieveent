<?php
$this->extend('layouts.admin');
$title = 'Pengguna';
$crumb = 'Akun admin & staf check-in';
$me = auth_user();
?>
<div class="grid-2">
  <div class="card">
    <div class="card-header"><h3><?= icon('shield') ?> Daftar pengguna</h3><span class="badge"><?= count($users) ?> akun</span></div>
    <div class="table-wrap">
      <table class="table table-cards">
        <thead><tr><th>Pengguna</th><th>Peran</th><th>Login terakhir</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): $isMe = (int) $u['id'] === (int) $me['id']; ?>
          <tr>
            <td class="td-main"><div class="person"><span class="avatar"><?= e(mb_strtoupper(mb_substr((string) $u['name'], 0, 1))) ?></span>
              <span><span class="person-name"><?= e($u['name']) ?><?= $isMe ? ' <span class="badge badge-info">Anda</span>' : '' ?></span><span class="person-sub">@<?= e($u['username']) ?><?= $u['email'] ? ' · ' . e($u['email']) : '' ?></span></span></div></td>
            <td data-label="Peran"><span class="badge <?= $u['role'] === 'admin' ? 'badge-primary' : 'badge-info' ?>"><?= e($u['role'] === 'admin' ? 'Admin' : 'Staf') ?></span>
              <?php if (!(int) $u['is_active']): ?><span class="badge badge-danger">Nonaktif</span><?php endif; ?></td>
            <td data-label="Login terakhir"><span class="small muted"><?= e($u['last_login_at'] ? time_ago($u['last_login_at']) : 'Belum pernah') ?></span></td>
            <td class="td-actions">
              <div class="flex gap-1" style="justify-content:flex-end">
                <button type="button" class="btn btn-sm btn-ghost" data-toggle="#edit-user-<?= (int) $u['id'] ?>"><?= icon('edit') ?></button>
                <?php if (!$isMe): ?>
                <form method="post" action="<?= e(route('admin.users.destroy', ['id' => $u['id']])) ?>" data-confirm="Hapus akun <?= e($u['username']) ?>?">
                  <?= csrf_field() ?><button class="btn btn-sm btn-ghost text-danger" type="submit"><?= icon('trash') ?></button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <tr id="edit-user-<?= (int) $u['id'] ?>" hidden>
            <td colspan="4" style="background:var(--surface-2)">
              <form method="post" action="<?= e(route('admin.users.update', ['id' => $u['id']])) ?>" class="flex gap-1 wrap items-center">
                <?= csrf_field() ?>
                <select class="select select-sm" name="role" style="width:auto"><?php foreach (App\Models\User::ROLES as $k => $l): ?><option value="<?= $k ?>" <?= $u['role'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
                <label class="switch" style="font-size:.85rem"><input type="checkbox" name="is_active" value="1" <?= (int) $u['is_active'] ? 'checked' : '' ?>> Aktif</label>
                <input class="input input-sm" type="password" name="password" placeholder="Password baru (opsional)" autocomplete="new-password" style="width:220px">
                <button class="btn btn-sm btn-primary" type="submit"><?= icon('save') ?> Simpan</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <form method="post" action="<?= e(route('admin.users.store')) ?>" class="card" data-loading-form>
    <?= csrf_field() ?>
    <div class="card-header"><h3><?= icon('user-plus') ?> Tambah pengguna</h3></div>
    <div class="card-body">
      <div class="form-group"><label class="label" for="u-name">Nama</label><input id="u-name" class="input <?= error('name') ? 'is-invalid' : '' ?>" name="name" value="<?= e(old('name')) ?>" required maxlength="100"><?= $this->partial('partials.field-error', ['field' => 'name']) ?></div>
      <div class="form-group"><label class="label" for="u-username">Username</label><input id="u-username" class="input <?= error('username') ? 'is-invalid' : '' ?>" name="username" value="<?= e(old('username')) ?>" required maxlength="40" autocomplete="off"><?= $this->partial('partials.field-error', ['field' => 'username']) ?></div>
      <div class="form-group"><label class="label" for="u-email">Email <span class="muted">(opsional)</span></label><input id="u-email" class="input <?= error('email') ? 'is-invalid' : '' ?>" name="email" value="<?= e(old('email')) ?>" maxlength="150"><?= $this->partial('partials.field-error', ['field' => 'email']) ?></div>
      <div class="form-group"><label class="label" for="u-role">Peran</label>
        <select id="u-role" class="select" name="role"><?php foreach (App\Models\User::ROLES as $k => $l): ?><option value="<?= $k ?>" <?= old('role', 'staff') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
        <div class="hint">Staf hanya bisa melihat peserta & melakukan check-in.</div></div>
      <div class="form-group"><label class="label" for="u-password">Password</label><input id="u-password" class="input <?= error('password') ? 'is-invalid' : '' ?>" type="password" name="password" required autocomplete="new-password"><div class="hint">Min. 8 karakter, huruf & angka.</div><?= $this->partial('partials.field-error', ['field' => 'password']) ?></div>
      <button class="btn btn-primary btn-block" type="submit"><span class="spinner"></span><?= icon('user-plus') ?> Tambah</button>
    </div>
  </form>
</div>
