<?php
$this->extend('layouts.public');
$title = $event['title'];
$description = $event['subtitle'] ?: str_limit((string) $event['description'], 150);
$themeKey = $event['theme'];
$oldExtra = old('extra', []);
$oldExtra = is_array($oldExtra) ? $oldExtra : [];
$quota = (int) ($event['quota'] ?? 0);
$showCount = setting('show_count_public', '1') === '1';
?>
<div class="container-sm">
  <section class="event-hero">
    <?php if ($isDraft): ?><span class="chip mb-1"><?= icon('eye') ?> Pratinjau draft — hanya terlihat oleh admin</span><?php endif; ?>
    <h1><?= e($event['title']) ?></h1>
    <?php if ($event['subtitle']): ?><div class="subtitle"><?= e($event['subtitle']) ?></div><?php endif; ?>
    <div class="chips">
      <?php if ($event['starts_at']): ?>
        <span class="chip"><?= icon('calendar') ?><?= e(day_id($event['starts_at']) . ', ' . date_id($event['starts_at'], false)) ?></span>
        <span class="chip"><?= icon('clock') ?><?= e(date('H:i', strtotime($event['starts_at']))) ?><?= $event['ends_at'] ? ' – ' . e(date('H:i', strtotime($event['ends_at']))) : '' ?> <?= e(date('T')) ?></span>
      <?php endif; ?>
      <?php if ($event['location']): ?><span class="chip"><?= icon('map-pin') ?><?= e($event['location']) ?></span><?php endif; ?>
    </div>
    <?php if ($showCount || $quota): ?>
    <div class="counter">
      <span class="avatars" aria-hidden="true"><i></i><i></i><i></i></span>
      <span><?= number_id($count) ?><?= $quota ? ' / ' . number_id($quota) : '' ?> peserta terdaftar</span>
    </div>
    <?php if ($quota): ?><div class="progress"><span style="width:<?= min(100, round($count / max(1, $quota) * 100)) ?>%"></span></div><?php endif; ?>
    <?php endif; ?>
  </section>

  <div class="card form-card">
    <div class="card-body">
      <?php if (!$open): ?>
        <div class="closed-box">
          <div class="big-icon"><?= icon('lock') ?></div>
          <h2>Pendaftaran tidak tersedia</h2>
          <p class="muted"><?= e($reason) ?></p>
          <a class="btn btn-soft" href="<?= e(url('/')) ?>"><?= icon('arrow-left') ?> Lihat event lainnya</a>
        </div>
      <?php else: ?>
        <?php if ($event['description']): ?>
          <p class="desc"><?= e($event['description']) ?></p>
          <hr>
        <?php endif; ?>
        <?php if (errors()): ?>
          <div class="alert alert-error"><?= icon('alert') ?><span>Beberapa isian belum sesuai. Silakan periksa kolom yang ditandai merah.</span></div>
        <?php endif; ?>
        <form method="post" action="<?= e(route('event.register', ['slug' => $event['slug']])) ?>" data-loading-form novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="ts" value="<?= e($formTs) ?>">
          <div class="hp-field" aria-hidden="true">
            <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
          </div>

          <div class="form-section-title">Data Diri</div>
          <div class="form-group">
            <label class="label" for="f-name">Nama lengkap <span class="req">*</span></label>
            <div class="input-icon"><?= icon('user') ?>
              <input id="f-name" class="input <?= error('name') ? 'is-invalid' : '' ?>" name="name" value="<?= e(old('name')) ?>" required maxlength="120" autocomplete="name" placeholder="Nama sesuai identitas">
            </div>
            <?= $this->partial('partials.field-error', ['field' => 'name']) ?>
          </div>
          <div class="form-group">
            <label class="label" for="f-wa">Nomor WhatsApp <span class="req">*</span></label>
            <div class="input-icon"><?= icon('phone') ?>
              <input id="f-wa" class="input <?= error('wa') ? 'is-invalid' : '' ?>" name="wa" value="<?= e(old('wa')) ?>" required maxlength="25" inputmode="tel" autocomplete="tel" placeholder="08xxxxxxxxxx">
            </div>
            <div class="hint">Pastikan aktif di WhatsApp — digunakan untuk konfirmasi & grup.</div>
            <?= $this->partial('partials.field-error', ['field' => 'wa']) ?>
          </div>
          <?php if ((int) $event['show_email']): ?>
          <div class="form-group">
            <label class="label" for="f-email">Email <?= (int) $event['require_email'] ? '<span class="req">*</span>' : '<span class="muted">(opsional)</span>' ?></label>
            <div class="input-icon"><?= icon('mail') ?>
              <input id="f-email" type="email" class="input <?= error('email') ? 'is-invalid' : '' ?>" name="email" value="<?= e(old('email')) ?>" maxlength="150" autocomplete="email" placeholder="nama@email.com" <?= (int) $event['require_email'] ? 'required' : '' ?>>
            </div>
            <?= $this->partial('partials.field-error', ['field' => 'email']) ?>
          </div>
          <?php endif; ?>
          <?php if ((int) $event['show_representative']): ?>
          <div class="form-group">
            <label class="label" for="f-rep"><?= e(App\Models\Event::representativeLabel($event)) ?> <?= (int) $event['require_representative'] ? '<span class="req">*</span>' : '<span class="muted">(opsional)</span>' ?></label>
            <div class="input-icon"><?= icon('building') ?>
              <input id="f-rep" class="input <?= error('representative') ? 'is-invalid' : '' ?>" name="representative" value="<?= e(old('representative')) ?>" maxlength="150" autocomplete="organization" <?= (int) $event['require_representative'] ? 'required' : '' ?>>
            </div>
            <?= $this->partial('partials.field-error', ['field' => 'representative']) ?>
          </div>
          <?php endif; ?>
          <?php if ((int) $event['show_address']): ?>
          <div class="form-group">
            <label class="label" for="f-address">Alamat <?= (int) $event['require_address'] ? '<span class="req">*</span>' : '<span class="muted">(opsional)</span>' ?></label>
            <div class="input-icon"><?= icon('map-pin') ?>
              <input id="f-address" class="input <?= error('address') ? 'is-invalid' : '' ?>" name="address" value="<?= e(old('address')) ?>" maxlength="255" autocomplete="street-address" <?= (int) $event['require_address'] ? 'required' : '' ?>>
            </div>
            <?= $this->partial('partials.field-error', ['field' => 'address']) ?>
          </div>
          <?php endif; ?>

          <?php if ($fields): ?>
            <div class="form-section-title mt-2">Informasi Tambahan</div>
            <?php foreach ($fields as $f): ?>
              <?= $this->partial('public.field', ['f' => $f, 'value' => $oldExtra[$f['key']] ?? ($f['type'] === 'checkbox' ? [] : '')]) ?>
            <?php endforeach; ?>
          <?php endif; ?>

          <button class="btn btn-primary btn-lg btn-block mt-2" type="submit">
            <span class="spinner"></span><?= icon('ticket') ?> Daftar & Dapatkan Tiket
          </button>
          <div class="privacy-note"><?= icon('shield') ?><span>Data Anda hanya digunakan panitia untuk keperluan acara ini.</span></div>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
