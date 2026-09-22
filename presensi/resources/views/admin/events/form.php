<?php
$this->extend('layouts.admin');
$isEdit = (int) $event['id'] > 0;
$title = $isEdit ? 'Edit Event' : 'Event Baru';
$crumb = $isEdit ? $event['title'] : 'Buat event & formulir pendaftaran';
$v = static function (string $k) use ($event) {
    $o = old($k, null);
    return $o !== null ? $o : ($event[$k] ?? '');
};
$dt = static function ($val): string {
    $val = (string) $val;
    if ($val === '') { return ''; }
    $ts = strtotime(str_replace('T', ' ', $val));
    return $ts ? date('Y-m-d\TH:i', $ts) : '';
};
$hasOld = old('title', null) !== null;
$chk = static function (string $k) use ($event, $hasOld): string {
    if ($hasOld) { return old($k, '') ? 'checked' : ''; }
    return (int) ($event[$k] ?? 0) ? 'checked' : '';
};
$fieldsJson = $hasOld ? (string) old('fields', '[]') : json_encode($fields, JSON_UNESCAPED_UNICODE);
$action = $isEdit ? route('admin.events.update', ['id' => $event['id']]) : route('admin.events.store');
$link = $isEdit ? full_url('e/' . $event['slug']) : '';
?>
<?php $this->section('actions') ?>
  <?php if ($isEdit): ?><a class="btn btn-ghost" href="<?= e($link) ?>" target="_blank" rel="noopener"><?= icon('eye') ?><span class="hide-sm">Pratinjau</span></a><?php endif; ?>
  <button class="btn btn-primary" type="submit" form="event-form"><?= icon('save') ?><span class="hide-sm">Simpan</span></button>
<?php $this->end() ?>

<?php if ($isEdit): ?>
<div class="stats">
  <div class="card stat"><div class="stat-icon c-violet"><?= icon('users') ?></div><div><div class="value"><?= number_id($stats['total']) ?></div><div class="label-s">Pendaftar</div></div></div>
  <div class="card stat"><div class="stat-icon c-emerald"><?= icon('user-check') ?></div><div><div class="value"><?= number_id($stats['checked_in']) ?></div><div class="label-s">Hadir</div></div></div>
  <div class="card stat" style="grid-column:span 2;min-width:0">
    <div class="stat-icon c-pink"><?= icon('link') ?></div>
    <div class="grow">
      <div class="label-s mb-1">Tautan pendaftaran</div>
      <div class="share-box"><code><?= e($link) ?></code>
        <button type="button" class="btn btn-sm btn-soft" data-copy="<?= e($link) ?>"><?= icon('copy') ?> Salin</button>
        <a class="btn btn-sm btn-wa" href="<?= e(wa_link('', 'Yuk daftar ' . $event['title'] . ': ' . $link)) ?>" target="_blank" rel="noopener"><?= icon('whatsapp') ?></a>
        <button type="button" class="btn btn-sm btn-ghost" data-qr-modal="<?= e($link) ?>" title="QR code pendaftaran"><?= icon('qr') ?></button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<form id="event-form" method="post" action="<?= e($action) ?>" data-loading-form>
  <?= csrf_field() ?>
  <div class="grid-2">
    <div class="stack">
      <div class="card">
        <div class="card-header"><h3><?= icon('info') ?> Informasi event</h3></div>
        <div class="card-body">
          <div class="form-group">
            <label class="label" for="title">Judul event <span class="req">*</span></label>
            <input id="title" class="input <?= error('title') ? 'is-invalid' : '' ?>" name="title" value="<?= e($v('title')) ?>" required maxlength="150" data-slug-source="#slug" placeholder="mis. Seminar Digital Marketing 2026">
            <?= $this->partial('partials.field-error', ['field' => 'title']) ?>
          </div>
          <div class="form-group">
            <label class="label" for="slug">Slug URL</label>
            <div class="input-icon"><?= icon('link') ?><input id="slug" class="input <?= error('slug') ? 'is-invalid' : '' ?>" name="slug" value="<?= e($v('slug')) ?>" maxlength="80" placeholder="otomatis dari judul" <?= $isEdit ? 'data-slug-locked' : '' ?>></div>
            <div class="hint">Alamat: <?= e(full_url('e/')) ?><b data-slug-preview><?= e($v('slug')) ?></b></div>
            <?= $this->partial('partials.field-error', ['field' => 'slug']) ?>
          </div>
          <div class="form-group">
            <label class="label" for="subtitle">Subjudul</label>
            <input id="subtitle" class="input" name="subtitle" value="<?= e($v('subtitle')) ?>" maxlength="200" placeholder="Kalimat singkat yang menarik">
            <?= $this->partial('partials.field-error', ['field' => 'subtitle']) ?>
          </div>
          <div class="form-group">
            <label class="label" for="description">Deskripsi</label>
            <textarea id="description" class="textarea" name="description" rows="4" maxlength="5000" placeholder="Informasi acara, pembicara, dress code, dll."><?= e($v('description')) ?></textarea>
            <?= $this->partial('partials.field-error', ['field' => 'description']) ?>
          </div>
          <div class="form-grid cols-2">
            <div class="form-group"><label class="label" for="starts_at">Mulai</label><input id="starts_at" type="datetime-local" class="input <?= error('starts_at') ? 'is-invalid' : '' ?>" name="starts_at" value="<?= e($dt($v('starts_at'))) ?>"><?= $this->partial('partials.field-error', ['field' => 'starts_at']) ?></div>
            <div class="form-group"><label class="label" for="ends_at">Selesai</label><input id="ends_at" type="datetime-local" class="input <?= error('ends_at') ? 'is-invalid' : '' ?>" name="ends_at" value="<?= e($dt($v('ends_at'))) ?>"><?= $this->partial('partials.field-error', ['field' => 'ends_at']) ?></div>
          </div>
          <div class="form-group mb-0">
            <label class="label" for="location">Lokasi</label>
            <div class="input-icon"><?= icon('map-pin') ?><input id="location" class="input" name="location" value="<?= e($v('location')) ?>" maxlength="200" placeholder="Nama gedung / Zoom / alamat"></div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h3><?= icon('list-checks') ?> Formulir pendaftaran</h3>
          <span class="muted small">Nama & No. WhatsApp selalu wajib</span>
        </div>
        <div class="card-body">
          <div class="form-section-title">Kolom bawaan</div>
          <div class="table-wrap mb-2">
            <table class="table">
              <thead><tr><th>Kolom</th><th>Tampilkan</th><th>Wajib</th></tr></thead>
              <tbody>
                <tr><td><b>Email</b></td><td><label class="switch"><input type="checkbox" name="show_email" value="1" <?= $chk('show_email') ?>></label></td><td><label class="switch"><input type="checkbox" name="require_email" value="1" <?= $chk('require_email') ?>></label></td></tr>
                <tr><td><b>Alamat</b></td><td><label class="switch"><input type="checkbox" name="show_address" value="1" <?= $chk('show_address') ?>></label></td><td><label class="switch"><input type="checkbox" name="require_address" value="1" <?= $chk('require_address') ?>></label></td></tr>
                <tr><td><b>Perwakilan</b><div class="mt-1"><input class="input input-sm" name="representative_label" value="<?= e($v('representative_label')) ?>" placeholder="Label: Instansi / Perwakilan" maxlength="60"></div></td><td><label class="switch"><input type="checkbox" name="show_representative" value="1" <?= $chk('show_representative') ?>></label></td><td><label class="switch"><input type="checkbox" name="require_representative" value="1" <?= $chk('require_representative') ?>></label></td></tr>
              </tbody>
            </table>
          </div>
          <div class="form-section-title">Kolom tambahan</div>
          <input type="hidden" name="fields" value="<?= e($fieldsJson) ?>" data-builder-input>
          <div class="builder" data-builder data-types="<?= e(json_encode(App\Models\Event::FIELD_TYPES, JSON_UNESCAPED_UNICODE)) ?>"></div>
          <button type="button" class="btn btn-soft mt-2" data-builder-add><?= icon('plus') ?> Tambah kolom</button>
        </div>
      </div>
    </div>

    <div class="stack">
      <div class="card">
        <div class="card-header"><h3><?= icon('power') ?> Pendaftaran</h3></div>
        <div class="card-body">
          <div class="form-group">
            <label class="label" for="status">Status</label>
            <select id="status" class="select" name="status">
              <?php foreach (App\Models\Event::STATUSES as $k => $label): ?>
                <option value="<?= e($k) ?>" <?= $v('status') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="hint">Draft hanya bisa dilihat admin (untuk uji coba).</div>
          </div>
          <div class="form-grid cols-2">
            <div class="form-group"><label class="label" for="quota">Kuota</label><input id="quota" class="input <?= error('quota') ? 'is-invalid' : '' ?>" name="quota" value="<?= e($v('quota')) ?>" inputmode="numeric" placeholder="Tanpa batas"><?= $this->partial('partials.field-error', ['field' => 'quota']) ?></div>
            <div class="form-group"><label class="label" for="closes_at">Batas daftar</label><input id="closes_at" type="datetime-local" class="input <?= error('closes_at') ? 'is-invalid' : '' ?>" name="closes_at" value="<?= e($dt($v('closes_at'))) ?>"><?= $this->partial('partials.field-error', ['field' => 'closes_at']) ?></div>
          </div>
          <label class="switch"><input type="checkbox" name="dedupe_wa" value="1" <?= $chk('dedupe_wa') ?>> Tolak pendaftaran ganda (No. WA sama)</label>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3><?= icon('whatsapp') ?> Setelah mendaftar</h3></div>
        <div class="card-body">
          <div class="form-group">
            <label class="label" for="group_link">Link grup WhatsApp</label>
            <input id="group_link" class="input <?= error('group_link') ? 'is-invalid' : '' ?>" name="group_link" value="<?= e($v('group_link')) ?>" maxlength="255" placeholder="https://chat.whatsapp.com/…">
            <div class="hint">Tombol "Gabung Grup" muncul di halaman tiket.</div>
            <?= $this->partial('partials.field-error', ['field' => 'group_link']) ?>
          </div>
          <div class="form-group">
            <label class="label" for="redirect_seconds">Buka grup otomatis (detik)</label>
            <input id="redirect_seconds" class="input" name="redirect_seconds" value="<?= e((string) $v('redirect_seconds')) ?>" inputmode="numeric" placeholder="0 = tidak otomatis">
            <?= $this->partial('partials.field-error', ['field' => 'redirect_seconds']) ?>
          </div>
          <div class="form-group mb-0">
            <label class="label" for="success_message">Pesan sukses</label>
            <textarea id="success_message" class="textarea" name="success_message" rows="3" maxlength="1000" placeholder="mis. Sampai jumpa di lokasi! Datang 15 menit sebelum acara."><?= e($v('success_message')) ?></textarea>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3><?= icon('palette') ?> Tema warna</h3></div>
        <div class="card-body">
          <div class="theme-picker">
            <?php foreach (themes() as $k => $t): ?>
              <label class="theme-opt"><input type="radio" name="theme" value="<?= e($k) ?>" <?= $v('theme') === $k ? 'checked' : '' ?>>
                <span class="sw" style="background:linear-gradient(135deg,<?= e($t['from']) ?>,<?= e($t['via']) ?>,<?= e($t['to']) ?>)"><?= e($t['label']) ?></span></label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <button class="btn btn-primary btn-lg btn-block" type="submit"><span class="spinner"></span><?= icon('save') ?> <?= $isEdit ? 'Simpan perubahan' : 'Buat event' ?></button>
    </div>
  </div>
</form>
