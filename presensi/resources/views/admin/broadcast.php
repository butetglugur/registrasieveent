<?php
$this->extend('layouts.admin');
$title = 'Pesan Massal';
$crumb = 'Kirim pengingat WhatsApp ke peserta — bertahap dengan jeda anti-blokir';
$tpl = (string) old('message', App\Controllers\Admin\BroadcastController::DEFAULT_TEMPLATE);
?>
<div class="alert alert-warning">
  <?= icon('alert') ?>
  <div><b>Perhatian — integrasi eksternal & risiko blokir.</b> Pesan dikirim lewat WA gateway pihak ketiga
    (<?= e(App\Services\Notifier::WA_PROVIDERS[setting('notify_wa_provider', 'none')] ?? '-') ?>) dan membawa data peserta.
    Semua pesan masuk <b>antrean berjeda</b> (<?= e(App\Services\WaThrottle::cfg('notify_wa_delay_min')) ?>–<?= e(App\Services\WaThrottle::cfg('notify_wa_delay_max')) ?> detik,
    istirahat <?= e(App\Services\WaThrottle::cfg('notify_wa_batch_rest')) ?> menit tiap <?= e(App\Services\WaThrottle::cfg('notify_wa_batch_size')) ?> pesan,
    maks. <?= e(App\Services\WaThrottle::cfg('notify_wa_hourly_limit')) ?>/jam &amp; <?= e(App\Services\WaThrottle::cfg('notify_wa_daily_limit')) ?>/hari).
    Kirim hanya ke peserta yang sudah setuju dihubungi, dan hindari pesan promosi.</div>
</div>

<?php if (!$ready): ?>
  <div class="card empty"><div class="empty-icon"><?= icon('power') ?></div><h3>Notifikasi WhatsApp belum aktif</h3>
    <p>Aktifkan saklar notifikasi & isi token WA gateway terlebih dahulu.</p>
    <a class="btn btn-primary" href="<?= e(route('admin.notifications')) ?>"><?= icon('settings') ?> Buka pengaturan notifikasi</a></div>
<?php else: ?>
<div class="grid-2">
  <form method="post" action="<?= e(route('admin.broadcast.store')) ?>" class="card" data-loading-form>
    <?= csrf_field() ?>
    <div class="card-header"><h3><?= icon('whatsapp') ?> Susun pesan</h3></div>
    <div class="card-body">
      <div class="form-group">
        <label class="label" for="b-event">Event</label>
        <select id="b-event" class="select <?= error('event_id') ? 'is-invalid' : '' ?>" name="event_id" data-nav-select="<?= e(route('admin.broadcast')) ?>?event=">
          <option value="">— Pilih event —</option>
          <?php foreach ($events as $ev): ?><option value="<?= (int) $ev['id'] ?>" <?= (int) ($event['id'] ?? 0) === (int) $ev['id'] ? 'selected' : '' ?>><?= e($ev['title']) ?></option><?php endforeach; ?>
        </select>
        <?= $this->partial('partials.field-error', ['field' => 'event_id']) ?>
      </div>
      <?php if ($event): ?>
      <div class="form-group">
        <label class="label">Penerima</label>
        <?php foreach (App\Controllers\Admin\BroadcastController::AUDIENCES as $k => $l): ?>
          <label class="choice"><input type="radio" name="audience" value="<?= $k ?>" <?= old('audience', 'all') === $k ? 'checked' : '' ?>>
            <span><b><?= e($l) ?></b> — <?= number_id($counts[$k]['n']) ?> nomor <span class="muted small">· perkiraan <?= e($counts[$k]['eta']) ?></span></span></label>
        <?php endforeach; ?>
      </div>
      <div class="form-group">
        <label class="label" for="b-msg">Isi pesan</label>
        <textarea id="b-msg" class="textarea mono <?= error('message') ? 'is-invalid' : '' ?>" name="message" rows="10" maxlength="2000"><?= e($tpl) ?></textarea>
        <div class="hint">Placeholder: {nama} {event} {tanggal} {lokasi} {kode} {link_tiket} {link_grup} {instansi}. Variasi acak: <code>{Halo|Hai}</code> — setiap penerima mendapat kalimat yang sedikit berbeda.</div>
        <?= $this->partial('partials.field-error', ['field' => 'message']) ?>
      </div>
      <label class="choice <?= error('confirm') ? 'is-invalid' : '' ?>"><input type="checkbox" name="confirm" value="1">
        <span>Saya memahami pesan akan dikirim ke nomor peserta melalui WA gateway pihak ketiga dan peserta telah setuju dihubungi.</span></label>
      <?= $this->partial('partials.field-error', ['field' => 'confirm']) ?>
      <?php endif; ?>
    </div>
    <?php if ($event): ?>
    <div class="card-footer flex" style="justify-content:flex-end"><button class="btn btn-primary" type="submit"><span class="spinner"></span><?= icon('zap') ?> Masukkan ke antrean</button></div>
    <?php endif; ?>
  </form>
  <div class="stack">
    <div class="card"><div class="card-body">
      <h3><?= icon('shield') ?> Tips anti-blokir</h3>
      <ul class="small text-2" style="padding-left:1.1rem;margin:0;display:grid;gap:.35rem">
        <li>Gunakan nomor khusus yang sudah "hangat" (aktif dipakai chat normal beberapa minggu).</li>
        <li>Minta peserta menyimpan nomor panitia — pesan ke kontak tersimpan jarang dilaporkan spam.</li>
        <li>Pakai variasi kalimat <code>{…|…}</code>, sebut nama peserta, hindari link berlebihan.</li>
        <li>Jangan menaikkan batas per jam untuk nomor baru; mulai kecil lalu naikkan bertahap.</li>
        <li>Aktifkan jam tenang agar tidak mengirim larut malam.</li>
      </ul>
    </div></div>
    <div class="card"><div class="card-body">
      <h3><?= icon('clock') ?> Antrean saat ini</h3>
      <p class="mb-1"><b><?= number_id($pending) ?></b> pesan WhatsApp menunggu dikirim.</p>
      <a class="btn btn-soft btn-sm" href="<?= e(route('admin.notifications', [], ['status' => 'pending'])) ?>"><?= icon('history') ?> Lihat antrean</a>
    </div></div>
  </div>
</div>
<?php endif; ?>
