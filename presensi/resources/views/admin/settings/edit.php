<?php
$this->extend('layouts.admin');
$title = 'Pengaturan';
$crumb = 'Identitas & perilaku aplikasi';
$v = static function (string $k) { $o = old($k, null); return $o !== null ? $o : (string) setting($k, ''); };
?>
<form method="post" action="<?= e(route('admin.settings.update')) ?>" data-loading-form>
  <?= csrf_field() ?>
  <div class="grid-2 even">
    <div class="card">
      <div class="card-header"><h3><?= icon('sparkles') ?> Identitas</h3></div>
      <div class="card-body">
        <div class="form-group"><label class="label" for="app_name">Nama aplikasi</label><input id="app_name" class="input <?= error('app_name') ? 'is-invalid' : '' ?>" name="app_name" value="<?= e($v('app_name')) ?>" required maxlength="60"><?= $this->partial('partials.field-error', ['field' => 'app_name']) ?></div>
        <div class="form-group"><label class="label" for="org_name">Nama organisasi</label><input id="org_name" class="input" name="org_name" value="<?= e($v('org_name')) ?>" maxlength="100" placeholder="mis. Digital Media Inspirasi"></div>
        <div class="form-group"><label class="label" for="tagline">Tagline halaman depan</label><input id="tagline" class="input" name="tagline" value="<?= e($v('tagline')) ?>" maxlength="200"></div>
        <div class="form-group"><label class="label" for="footer_text">Teks footer</label><input id="footer_text" class="input" name="footer_text" value="<?= e($v('footer_text')) ?>" maxlength="200" placeholder="© 2026 Nama Organisasi"></div>
        <div class="form-group mb-0"><label class="label">Tema default</label>
          <div class="theme-picker">
            <?php foreach (themes() as $k => $t): ?>
              <label class="theme-opt"><input type="radio" name="default_theme" value="<?= e($k) ?>" <?= $v('default_theme') === $k ? 'checked' : '' ?>>
                <span class="sw" style="background:linear-gradient(135deg,<?= e($t['from']) ?>,<?= e($t['via']) ?>,<?= e($t['to']) ?>)"><?= e($t['label']) ?></span></label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
    <div class="stack">
      <div class="card">
        <div class="card-header"><h3><?= icon('home') ?> Halaman depan</h3></div>
        <div class="card-body">
          <label class="choice"><input type="radio" name="home_mode" value="list" <?= $v('home_mode') !== 'event' ? 'checked' : '' ?>><span><b>Daftar event</b><br><span class="muted small">Tampilkan semua event yang dibuka/ditutup.</span></span></label>
          <label class="choice"><input type="radio" name="home_mode" value="event" <?= $v('home_mode') === 'event' ? 'checked' : '' ?>><span><b>Langsung ke satu event</b><br><span class="muted small">Cocok untuk QR code di poster/banner.</span></span></label>
          <select class="select mt-1 <?= error('home_event_id') ? 'is-invalid' : '' ?>" name="home_event_id" aria-label="Event halaman depan">
            <option value="">— Pilih event —</option>
            <?php foreach ($events as $ev): ?><option value="<?= (int) $ev['id'] ?>" <?= (string) $v('home_event_id') === (string) $ev['id'] ? 'selected' : '' ?>><?= e($ev['title']) ?></option><?php endforeach; ?>
          </select>
          <?= $this->partial('partials.field-error', ['field' => 'home_event_id']) ?>
          <label class="switch mt-2"><input type="checkbox" name="show_count_public" value="1" <?= $v('show_count_public') !== '0' ? 'checked' : '' ?>> Tampilkan jumlah peserta di halaman publik</label>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><h3><?= icon('shield') ?> Keamanan & format</h3></div>
        <div class="card-body">
          <div class="form-grid cols-2">
            <div class="form-group"><label class="label" for="wa_country_code">Kode negara WA</label><input id="wa_country_code" class="input <?= error('wa_country_code') ? 'is-invalid' : '' ?>" name="wa_country_code" value="<?= e($v('wa_country_code')) ?>" inputmode="numeric" maxlength="4"><div class="hint">62 = Indonesia. 08xx → 628xx.</div><?= $this->partial('partials.field-error', ['field' => 'wa_country_code']) ?></div>
            <div class="form-group"><label class="label" for="submit_limit">Maks. daftar / IP / 10 menit</label><input id="submit_limit" class="input <?= error('submit_limit') ? 'is-invalid' : '' ?>" name="submit_limit" value="<?= e($v('submit_limit')) ?>" inputmode="numeric"><div class="hint">Naikkan bila peserta memakai Wi-Fi yang sama.</div><?= $this->partial('partials.field-error', ['field' => 'submit_limit']) ?></div>
          </div>
        </div>
      </div>
      <button class="btn btn-primary btn-lg btn-block" type="submit"><span class="spinner"></span><?= icon('save') ?> Simpan pengaturan</button>
    </div>
  </div>
</form>
